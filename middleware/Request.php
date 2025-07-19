<?php

namespace Blink\Middleware;

use Blink\Exception\ClassNotFound;
use Blink\Middleware\Exception\InvalidMiddlewareReturn;
use function Blink\stringify;

/**
 * Request middleware
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Request {
	/**
     * List of middleware that will run on every request.
	 * 
	 * This is ran before routing happend.
     *
     * @var array<int, class-string|string>
     */
	protected static array $middleware = array(
		// Define your request middleware here.
	);

	/**
	 * Ran on every request, before routing happend.
	 * 
	 * Route and args property of Request is not populated for this
	 * call, since this is handled before routing. 
	 * 
	 * @param	\Blink\Http\Request	$request	The intercepted request.
	 * @return	\Blink\Http\Request	Return back the request.
	 */
	public static function handle(\Blink\Http\Request $request): \Blink\Http\Request {
		foreach (static::$middleware as $middleware) {
			if (!class_exists($middleware))
				throw new ClassNotFound($middleware);

			$handled = $middleware::handle($request);

			if ($handled === null) {
				// Does not return anything, fallback to current request data.
				$handled = $request;
			} else {
				$request = $handled;
			}

			if (!($request instanceof \Blink\Http\Request))
				throw new InvalidMiddlewareReturn($middleware, \Blink\Http\Request::class, stringify($request));
		}

		return $request;
	}
}
