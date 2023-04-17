<?php
/**
 * Request.php
 * 
 * Request middleware
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Middleware;
use Blink\Exception\ClassNotFound;
use Blink\Middleware\Exception\InvalidMiddlewareReturn;

class Request {
	/**
     * List of middleware that will run on every request.
	 * 
	 * This is ran before routing happend.
     *
     * @var array<int, class-string|string>
     */
	protected static Array $middleware = Array(
		// Define your request middleware here.
	);

	/**
	 * Ran on every request, before routing happend.
	 * 
	 * Route and args property of Request is not populated for this
	 * call, since this is handled before routing. 
	 * 
	 * @param	\Blink\Request	$request	The intercepted request.
	 * @return	\Blink\Request	Return back the request.
	 */
	public static function handle(\Blink\Request $request): \Blink\Request {
		
		foreach (static::$middleware as $middleware) {
			if (!class_exists($middleware))
				throw new ClassNotFound($middleware);

			$request = $middleware::handle($request);

			if (!$middleware instanceof \Blink\Request)
				throw new InvalidMiddlewareReturn($request, \Blink\Request::class, get_class($request));
		}

		return $request;
	}
}
