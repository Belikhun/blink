<?php

namespace Blink\Http\Router;

use Blink\Essence;
use Blink\Ether;
use Blink\Essence\Exception\EssenceCallArgumentMismatch;
use Blink\Essence\Exception\EssenceCallMissingParam;
use Blink\Http\Request;
use Blink\Http\Router;
use Blink\Exception\BaseException;
use Blink\Http\Exception\RouteInvalidCallback;
use Blink\Http\Exception\RouteArgumentMismatch;
use Blink\Http\Exception\RouteCallbackInvalidParam;

/**
 * Represent a valid route for our router to go.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
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
	 * Router lifecycle middleware references attached to this route.
	 *
	 * @var	string[]
	 */
	protected array $middleware = array();

	/**
	 * Router lifecycle middleware references this route opts out of.
	 *
	 * @var	string[]
	 */
	protected array $excludedMiddleware = array();

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

		if (!is_callable($this -> action))
			throw new RouteInvalidCallback($this -> action, $this -> uri);

		try {
			return Essence::call($this -> action, $args, array(
				Request::class => $request,
				Route::class => $this
			));
		} catch (EssenceCallMissingParam $e) {
			// A handler asking for a URI parameter the pattern never captures
			// is a routing mistake, so it reads as one rather than as a
			// container failure.
			throw new RouteCallbackInvalidParam($this -> uri, $e -> data["name"]);
		} catch (EssenceCallArgumentMismatch $e) {
			throw new RouteArgumentMismatch($this, $e -> getMessage());
		} catch (\ArgumentCountError $e) {
			throw new BaseException(ROUTE_CALLBACK_ARGUMENTCOUNT_ERROR, $e -> getMessage(), 500);
		}
	}

	/**
	 * Attach router lifecycle middleware to this route, on top of whatever is
	 * registered globally. Takes class names, aliases and group names alike.
	 *
	 * @param	string	...$middleware
	 * @return	static
	 */
	public function middleware(string ...$middleware) {
		Ether::assertRouterLifecycleMiddleware(...$middleware);

		foreach ($middleware as $reference) {
			if (!in_array($reference, $this -> middleware, true))
				$this -> middleware[] = $reference;
		}

		return $this;
	}

	/**
	 * Opt this route out of router lifecycle middleware that would otherwise
	 * run, whether it came from the global stack or a group.
	 *
	 * @param	string	...$middleware
	 * @return	static
	 */
	public function withoutMiddleware(string ...$middleware) {
		Ether::assertRouterLifecycleMiddleware(...$middleware);

		foreach ($middleware as $reference) {
			if (!in_array($reference, $this -> excludedMiddleware, true))
				$this -> excludedMiddleware[] = $reference;
		}

		return $this;
	}

	/**
	 * @return	string[]
	 */
	public function getMiddleware(): array {
		return $this -> middleware;
	}

	/**
	 * @return	string[]
	 */
	public function getExcludedMiddleware(): array {
		return $this -> excludedMiddleware;
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
