<?php

namespace Router;

use Blink\Exception\BaseException;
use Blink\Exception\RouteArgumentMismatch;
use Blink\Exception\RouteCallbackInvalidParam;
use Blink\Request;

/**
 * Route.php
 * 
 * Represent a valid route for our router to go.
 * 
 * @author    Belikhun
 * @since     2.0.0
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
	public Array $verbs;

	/**
	 * Route URI
	 * @var	string
	 */
	public String $uri;

	/**
	 * Callback action for this route if matched
	 * @var	array|string|callable
	 */
	public $action;

	/**
	 * Route additional arguments taken from request URI.
	 * @var array
	 */
	public $args = Array();

	/**
	 * Route priority.
	 * Higher mean will be checked and executed first.
	 */
	public $priority = 0;

	/**
	 * Construct a new Route object
	 * 
     * @param  array			$verbs
     * @param  string			$uri
     * @param  callable			$action
	 */
	public function __construct(Array $verbs, String $uri, Callable $action) {
		$this -> verbs = $verbs;
		$this -> uri = $uri;
		$this -> action = $action;
	}

	/**
	 * Call to the action of this Route. Return the result
	 * of the callback
	 * 
	 * @param	string[]	$args
	 * @return	mixed
	 */
	public function callback(String $path, String $method, Array $args) {
		$this -> args = $args;
		$request = new Request($this, $method, $path, $args, $_GET, $_POST, getallheaders(), $_FILES);

		if (is_callable($this -> action)) {
			if (is_array($this -> action)) {
				$class = new \ReflectionClass($this -> action[0]);
				$info = $class -> getMethod($this -> action[1]);
			} else {
				$info = new \ReflectionFunction($this -> action);
			}

			$params = $info -> getParameters();
			$callArgs = Array();

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

				if (!isset($args[$name]))
					throw new RouteCallbackInvalidParam($this -> uri, $name);

				$callArgs[] = $args[$name];
				unset($args[$name]);
			}

			try {
				return call_user_func_array($this -> action, $callArgs);
			} catch (\TypeError $e) {
				$message = $e -> getMessage();
				$traces = $e -> getTrace();
				$matches = Array();

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
			if ($this -> action instanceof \Closure)
				$callbackName = "Closure";
			else if (is_array($this -> action))
				$callbackName = implode("::", $this -> action);
			else
				$callbackName = (String) $this -> action;

			throw new BaseException(ROUTE_CALLBACK_INVALID, "Callback [{$callbackName}] for route \"{$this -> uri}\" is missing or not callable.", 500);
		}
	}

	public function __toString() {
		$verb = (count($this -> verbs) === count(\Router::$verbs))
			? "ANY"
			: implode(" ", $this ->  verbs);

		return str_pad($this -> priority, 2, "0", STR_PAD_LEFT)
			. " " . str_pad($verb, 6, " ", STR_PAD_LEFT)
			. " " . $this -> uri;
	}
}