<?php

namespace Blink;

use ReflectionClass;
use ReflectionMethod;
use ReflectionAttribute;
use Blink\Exception\BaseException;
use Blink\Exception\ClassNotFound;
use Blink\Exception\RouteInvalidResponse;
use Blink\Exception\RouteNotFound;
use Blink\Metric\Timing;
use Blink\Response\JsonResponse;
use Blink\Router\Attribute\HttpMethod;
use Blink\Router\Route;

/**
 * Router.php
 *
 * Router interface.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Router {
	/**
	 * All routes for this router.
	 *
	 * @var	Route[]
	 */
	protected static $routes = array();

	/**
	* All of the verbs supported by the router.

	* @var	string[]
	*/
	public static $verbs = array("GET", "HEAD", "POST", "PUT", "PATCH", "DELETE");

	/**
	 * Currently active route.
	 * @var	Route
	 */
	public static $active = null;

	/**
	 * Currently processing plugin.
	 *
	 * @var	?string
	 */
	public static ?string $processingFile = null;

	/**
	* Register a new GET route with the router.
	*
	* @param  string			$uri
	* @param  string|callable	$action
	*/
	public static function GET($uri, $action, $priority = 0) {
		return static::match("GET", $uri, $action, $priority);
	}

	/**
	* Register a new POST route with the router.
	*
	* @param  string			$uri
	* @param  string|callable	$action
	*/
    public static function POST($uri, $action, $priority = 0) {
		return static::match("POST", $uri, $action, $priority);
    }

	/**
	* Register a new PATCH route with the router.
	*
	* @param  string			$uri
	* @param  string|callable	$action
	*/
    public static function PATCH($uri, $action, $priority = 0) {
		return static::match("PATCH", $uri, $action, $priority);
    }

	/**
	* Register a new DELETE route with the router.
	*
	* @param  string			$uri
	* @param  string|callable	$action
	*/
    public static function DELETE($uri, $action, $priority = 0) {
		return static::match("DELETE", $uri, $action, $priority);
    }

	/**
	* Register a new route with the router that
	* match every methods.
	*
	* @param  string			$uri
	* @param  string|callable	$action
	*/
    public static function ANY($uri, $action, $priority = 0) {
		return static::match(static::$verbs, $uri, $action, $priority);
    }

	/**
	* Register a new route.
	*
	* @param  array|string		$methods
	* @param  string			$uri
	* @param  string|callable	$action
	* @return Route
	*/
	public static function match($methods, $uri, $action, $priority = 0) {
		if (is_string($methods))
			$methods = array($methods);

		foreach ($methods as $method)
			if (!in_array($method, static::$verbs))
				throw new BaseException(-1, "HTTP Method \"{$method}\" is not supported!");

		$route = new Route($methods, $uri, $action, $priority);
		$route -> file = static::$processingFile;
		static::$routes[] = $route;

		return $route;
	}

	public static function getRoutes() {
		return static::$routes;
	}

	/**
	 * Register all defined routes in this controller.
	 * This function will scan all methods inside the controller that has the {@link Route} attribute defined.
	 *
	 * @param	class-string	$controller		The controller class name.
	 */
	public static function useController(string $controller) {
		if (!class_exists($controller))
			throw new ClassNotFound($controller);

		$refClass = new ReflectionClass($controller);

		/** @var ReflectionMethod[] */
		$methods = $refClass -> getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC);

		foreach ($methods as $method) {
			// We skip methods that's in the parent class.
			if ($method -> class !== $controller)
				continue;

			$uris = array();
			$methods = array();
			$priority = 0;

			/** @var ReflectionAttribute[] */
			$attributes = $method -> getAttributes(\Blink\Router\Attribute\Route::class);

			if (empty($attributes))
				continue;

			// Try getting the priority attribute from method.
			$priorityAttr = $method -> getAttributes(\Blink\Router\Attribute\RoutePriority::class);
			if (!empty($priorityAttr)) {
				$priorityAttr = array_pop($priorityAttr);
				$priority = intval($priorityAttr -> getArguments()[0]);
			}

			foreach ($attributes as $attribute) {
				$args = $attribute -> getArguments();
				$uris[] = $args[0];

				/** @var ReflectionAttribute[] */
				$methodAttrs = $method -> getAttributes(
					HttpMethod::class,
					ReflectionAttribute::IS_INSTANCEOF
				);

				foreach ($methodAttrs as $attr) {
					$class = $attr -> getName();

					if ($class === HttpMethod::class) {
						$verbs = array_map("strtoupper", $attr -> getArguments());
						$methods = array_merge($methods, $verbs);
					} else {
						// Child of HttpMethod. Get VERB from class directly.
						$methods[] = $attr -> getName()::VERB;
					}
				}
			}

			if (empty($methods)) {
				foreach ($uris as $uri)
					static::ANY($uri, [$controller, $method -> getName()], $priority);
			} else {
				foreach ($uris as $uri)
					static::match($methods, $uri, [$controller, $method -> getName()], $priority);
			}
		}
	}

	/**
	 * Handle the requested path.
	 *
	 * @param	string	$path
	 * @param	string	$method
	 */
	public static function route(string $path, string $method) {
		$args = array();
		$found = false;
		$routingTiming = new Timing("routing");

		// Up case method just to be sure
		$method = strtoupper($method);

		$request = new Request(
			$method, $path, $args,
			$_GET, $_POST, getallheaders(),
			$_COOKIE, $_FILES);

		$path = $request -> path;
		$method = $request -> method;

		// Sort routes by priority
		usort(static::$routes, function ($a, $b) {
			return $b -> priority <=> $a -> priority;
		});

		foreach (static::$routes as $route) {
			if (!in_array($method, $route -> verbs))
				continue;

			if (!static::isRouteMatch($route, $path, $args))
				continue;

			$found = true;
			$routingTiming -> time();
			static::$active = $route;

			// Update the request instance.
			$request -> route = $route;
			$request -> args = $args;

			$data = $route -> callback($request);
			$response = null;

			if (is_callable($data)) {
				// Get the actual response data from callable.
				$data = $data();
			}

			if (is_string($data) || is_numeric($data) || is_bool($data)) {
				// Response the data as string.
				$response = (string) $data;
			} else if (is_object($data) || is_array($data)) {
				// Response the data as object.
				$response = (!($data instanceof Response))
					? new JsonResponse($data)
					: $data;
			} else if ($data === null) {
				// Just response empty string.
				$response = "";
			}

			if ($response === null)
				throw new RouteInvalidResponse($route -> uri, stringify($response));

			if (!($response instanceof Response))
				$response = new Response($response);

			static::handleResponse($route, $response);
			break;
		}

		if (!$found) {
			$routingTiming -> time();
			throw new RouteNotFound($path, $method);
		}
	}

	/**
	 * Parse URI to array of tokens
	 *
	 * @param      string		$uri
	 * @return     string[]
	 */
	public static function uriTokens(string $uri) {
		$uri = ltrim($uri, "/");

		if (empty($uri))
			return array();

		return explode("/", str_replace("\\", "/", $uri));
	}

	/**
	 * Check if Route match current URI
	 *
	 * @param		Route		$route
	 * @param		string		$path
	 * @return		bool
	 */
	protected static function isRouteMatch(Route $route, string $path, array &$args = array()) {
		$pathTokens = static::uriTokens($path);
		$uriTokens = static::uriTokens($route -> uri);

		if (in_array("**", $uriTokens)) {
			// If route contains match rest, number of tokens don't
			// need to match the exact same count.
			if (count($pathTokens) < count($uriTokens))
				return false;
		} else {
			if (count($pathTokens) !== count($uriTokens))
				return false;
		}

		foreach ($uriTokens as $i => $token) {
			if (strlen($token) >= 2 && $token[0] === "{" && substr($token, -1) === "}") {
				$key = trim($token, "{}");

				if (!isset($pathTokens[$i]))
					continue;

				$value = $pathTokens[$i];

				// Try to parse value into int or float.
				if (is_float($value))
					$value = floatval($value);
				else if (is_numeric($value))
					$value = intval($value);

				$args[$key] = $value;
				continue;
			}

			// Check block
			if ($token === "*")
				continue;

			if ($token === "**") {
				// Pretend the rest are matched.
				return true;
			} else if ($token !== $pathTokens[$i]) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Handle response returned from route callback.
	 *
	 * @param Response $response
	 */
	protected static function handleResponse(Route $route, Response $response) {
		// At this point, we should clean all output buffer to make sure
		// our response output are sent to the client.
		while (ob_get_level())
			ob_end_clean();

		$response -> header("X-Powered-By", "PHP/" . phpversion() . " Blink/" . \Blink::version());
		echo $response -> serve();
		return;
	}
}
