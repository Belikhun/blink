<?php

namespace Blink\Router;

use Blink\Exception\RouteInvalidCallback;
use Blink\Request;
use Blink\Router;
use Blink\Exception\BaseException;
use Blink\Exception\RouteArgumentMismatch;
use Blink\Exception\RouteCallbackInvalidParam;

/**
 * Route.php
 *
 * Represent a valid route for our router to go.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

class Route {
	/**
	 * All the verbs for this route.
	 * @var string[]
	 */
	public array $verbs;

	/**
	 * Route URI
	 * @var	string
	 */
	public string $uri;

	/**
	 * Callback action for this route if matched
	 * @var	array|string|callable
	 */
	public $action;

	/**
	 * Route additional arguments taken from request URI.
	 * @var array
	 */
	public array $args = array();

	/**
	 * Route priority.
	 * Higher mean will be checked and executed first.
	 */
	public int $priority = 0;

	/**
	 * The file name that registered this route.
	 *
	 * @param ?string
	 */
	public ?string $file = null;

	/**
	 * Construct a new Route object
	 *
     * @param  array			$verbs
     * @param  string			$uri
     * @param  callable|array	$action
     * @param  int				$priority
	 */
	public function __construct(array $verbs, string $uri, Callable|array $action, int $priority = 0) {
		$this -> verbs = $verbs;
		$this -> uri = $uri;
		$this -> action = $action;
		$this -> priority = $priority + $this -> tokenWeight();
	}

	public function tokenWeight() {
		$weight = 0;
		$tokens = Router::uriTokens($this -> uri);

		foreach ($tokens as $token) {
			if (strlen($token) >= 2 && $token[0] === "{" && substr($token, -1) === "}") {
				// Argument match weight 1 point only, this is to make sure path with
				// literal match will come first.
				$weight += 1;
				continue;
			} else if ($token === "*") {
				$weight += 1;
				continue;
			} else if ($token === "**") {
				// Don't count all match to weight.
				continue;
			}

			// Literal match weight 2 points.
			$weight += 2;
		}

		return $weight;
	}

	/**
	 * Call to the action of this Route. Return the result
	 * of the callback
	 *
	 * @param	Request		$request
	 * @return	mixed
	 */
	public function callback(Request $request) {
		$args = $this -> args = $request -> args;

		if (is_callable($this -> action)) {
			if (is_array($this -> action)) {
				$class = new \ReflectionClass($this -> action[0]);
				$info = $class -> getMethod($this -> action[1]);
			} else {
				$info = new \ReflectionFunction($this -> action);
			}

			$params = $info -> getParameters();
			$callArgs = array();

			foreach ($params as $param) {
				$type = $param -> getType();
				$name = $param -> getName();

				if ($type && $type instanceof \ReflectionNamedType) {
					if ($type -> getName() === Request::class) {
						$callArgs[] = $request;
						continue;
					}
				}

				if ($param -> isVariadic()) {
					// Merge left over args and end processing.
					$callArgs = array_merge($callArgs, $args);
					break;
				}

				if (!isset($args[$name])) {
					// Check if our param here is optional?

					if (!$param -> isOptional())
						throw new RouteCallbackInvalidParam($this -> uri, $name);
					else
						$args[$name] = $param -> getDefaultValue();
				}

				$callArgs[] = $args[$name];
				unset($args[$name]);
			}

			try {
				return call_user_func_array($this -> action, $callArgs);
			} catch (\TypeError $e) {
				$message = $e -> getMessage();
				$traces = $e -> getTrace();
				$matches = array();

				// Determine that error was thrown by calling Route handler
				// Because this catch can be triggered somewhere inside handler
				if ($traces[1]["function"] === "call_user_func_array") {
					if (preg_match("/(Argument (\d+) passed to .+) must be/m", $message, $matches)) {
						$key = array_keys($args)[((int) $matches[2]) - 1];
						$message = str_replace($matches[1], "Value of \"$key\"", $message);

						throw new RouteArgumentMismatch($this, $message);
					}
				}

				throw $e;
			} catch (\ArgumentCountError $e) {
				throw new BaseException(ROUTE_CALLBACK_ARGUMENTCOUNT_ERROR, $e -> getMessage(), 500);
			}
		} else {
			throw new RouteInvalidCallback($this -> action, $this -> uri);
		}
	}

	public function __toString() {
		$verb = (count($this -> verbs) === count(Router::$verbs))
			? "ANY"
			: implode(" ", $this ->  verbs);

		return str_pad($this -> priority, 2, "0", STR_PAD_LEFT)
			. " " . str_pad($verb, 16, " ", STR_PAD_LEFT)
			. " " . str_pad($this -> file ?? "unknown_file", 24, " ", STR_PAD_LEFT)
			. " " . $this -> uri;
	}
}
