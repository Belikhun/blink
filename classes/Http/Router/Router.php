<?php

namespace Blink\Http;

use Blink;
use Blink\Ether;
use Blink\Ether\Request\RequestContext;
use Blink\Ether\Response\ResponseContext;
use Blink\Ether\Route\RouteContext;
use Blink\Http\Request;
use Blink\Http\Response;
use Blink\Metric\RouteMetric;
use Blink\Metric\TimingMetric;
use ReflectionClass;
use ReflectionMethod;
use ReflectionAttribute;
use Blink\Exception\BaseException;
use Blink\Exception\ClassNotFound;
use Blink\Http\Exception\RouteInvalidResponse;
use Blink\Http\Exception\RouteNotFound;
use Blink\Http\Response\JsonResponse;
use Blink\Http\Router\Attribute\HttpMethod;
use Blink\Http\Router\Attribute\Middleware as MiddlewareAttribute;
use Blink\Http\Router\Attribute\WithoutMiddleware as WithoutMiddlewareAttribute;
use Blink\Http\Router\Route;
use function Blink\requestHeaders;
use function Blink\stringify;

/**
 * Router interface.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
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
	 *
	 * @var	string[]
	 */
	public static $verbs = array("GET", "HEAD", "POST", "PUT", "PATCH", "DELETE");

	/**
	 * Currently active route.
	 *
	 * @var	Route
	 */
	public static $active = null;

	/**
	 * Currently processing file.
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
	 * This function will scan all methods inside the controller that has the {@see Route} attribute defined.
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
			$routeMiddleware = array();
			$routeExcludedMiddleware = array();

			/** @var ReflectionAttribute[] */
			$attributes = $method -> getAttributes(\Blink\Http\Router\Attribute\Route::class);

			if (empty($attributes))
				continue;

			// Try getting the priority attribute from method.
			$priorityAttr = $method -> getAttributes(\Blink\Http\Router\Attribute\RoutePriority::class);
			if (!empty($priorityAttr)) {
				$priorityAttr = array_pop($priorityAttr);
				$priority = intval($priorityAttr -> getArguments()[0]);
			}

			foreach ($method -> getAttributes(MiddlewareAttribute::class) as $attribute)
				$routeMiddleware = array_merge($routeMiddleware, $attribute -> getArguments());

			foreach ($method -> getAttributes(WithoutMiddlewareAttribute::class) as $attribute)
				$routeExcludedMiddleware = array_merge($routeExcludedMiddleware, $attribute -> getArguments());

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

			foreach ($uris as $uri) {
				$route = empty($methods)
					? static::ANY($uri, [$controller, $method -> getName()], $priority)
					: static::match($methods, $uri, [$controller, $method -> getName()], $priority);

				if (!empty($routeMiddleware))
					$route -> middleware(...$routeMiddleware);

				if (!empty($routeExcludedMiddleware))
					$route -> withoutMiddleware(...$routeExcludedMiddleware);
			}
		}
	}

	/**
	 * Load every route definition file in a directory, in alphabetical order.
	 *
	 * Each file runs in a function of its own so a variable one leaves behind
	 * cannot reach the next.
	 */
	public static function loadRoutesFrom(string $directory) {
		$files = glob(rtrim(str_replace("\\", "/", $directory), "/") . "/*.php");

		if (empty($files))
			return;

		sort($files);

		foreach ($files as $file) {
			(function ($path) {
				static::$processingFile = pathinfo($path, PATHINFO_FILENAME);
				require_once $path;
			})($file);

			static::$processingFile = null;
		}
	}

	/**
	 * Handle the requested path.
	 *
	 * @param	string	$path
	 * @param	string	$method
	 */
	public static function route(string $path, string $method) {
		$request = new Request(
			strtoupper($method), $path, array(),
			$_GET, $_POST, requestHeaders(),
			$_COOKIE, $_FILES);

		$response = static::dispatch($request);
		static::handleResponse($response, static::$active);
	}

	/**
	 * Run a request through the router and return the response without
	 * emitting it.
	 *
	 * @throws	RouteNotFound	when no registered route matches.
	 */
	public static function dispatch(Request $request): Response {
		$args = array();
		$routingTiming = new TimingMetric("routing");

		$routeContext = new RouteContext($request);
		$routeContext -> isOptionRequest = (strtoupper($request -> method) === "OPTIONS");

		Ether::dispatchRoute($routeContext, static function (RouteContext $context) {
			return $context;
		});

		// Route middleware is allowed to swap the request out, so everything
		// below reads from the context rather than the original.
		$request = $routeContext -> request;
		$path = $request -> path;
		$method = strtoupper($request -> method);
		$isOptionRequest = ($method === "OPTIONS");

		// Sort routes by priority
		usort(static::$routes, function ($a, $b) {
			return $b -> priority <=> $a -> priority;
		});

		foreach (static::$routes as $route) {
			if (!$isOptionRequest && !in_array($method, $route -> verbs))
				continue;

			if (!static::isRouteMatch($route, $path, $args))
				continue;

			$routingTiming -> time();
			static::$active = $route;
			$routeTiming = new RouteMetric($route);

			// Update the request instance.
			$request -> route = $route;
			$request -> args = $args;
			$response = null;

			$requestContext = new RequestContext($request, $route);
			$requestContext -> authenticatedByHeader = $routeContext -> authenticatedByHeader;
			$requestContext -> isCrossSite = $routeContext -> isCrossSite;
			$requestContext -> isOptionRequest = $isOptionRequest;

			$data = Ether::dispatchRequest($route, $requestContext, static function (RequestContext $context) {
				if ($context -> isOptionRequest)
					return null;

				return $context -> route -> callback($context -> request);
			});

			$request = $requestContext -> request;

			if (!$isOptionRequest) {
				// Only a closure counts as a response to be produced later.
				// `is_callable()` alone would not do: it answers true for the
				// name of any PHP function, so a handler returning the string
				// "reset" or "count" would have that function called instead
				// of the word being sent. An array is data, and gets encoded.
				if ($data instanceof \Closure) {
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

				if ($response === null) {
					$routeTiming -> time(-1);
					throw new RouteInvalidResponse($route -> uri, stringify($response));
				}

				if (!($response instanceof Response))
					$response = new Response($response);
			} else {
				// A preflight never reaches the handler, so an empty response
				// is all the CORS middleware needs to put its headers on.
				$response = new Response();
			}

			$response -> request = $request;

			$responseContext = new ResponseContext($request, $route, $response);
			$responseContext -> authenticatedByHeader = $requestContext -> authenticatedByHeader;
			$responseContext -> isCrossSite = $requestContext -> isCrossSite;
			$responseContext -> isOptionRequest = $requestContext -> isOptionRequest;

			$response = Ether::dispatchResponse($route, $responseContext, static function (ResponseContext $context) {
				return $context -> response;
			});

			$request = $responseContext -> request;
			$routeTiming -> time($response -> getStatus(), $request, $response);
			return $response;
		}

		$routingTiming -> time();
		throw new RouteNotFound($path, $method);
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
	 * @param	Response	$response
	 * @param	Route		$route
	 */
	protected static function handleResponse(Response $response, Route $route) {
		// At this point, we should clean all output buffer to make sure
		// our response output are sent to the client.
		while (ob_get_level())
			ob_end_clean();

		$response -> header("X-Powered-By", "PHP/" . phpversion() . " Blink/" . Blink::version());
		echo $response -> serve();
		return;
	}
}
