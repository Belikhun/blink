<?php
/**
 * Response.php
 * 
 * Response middleware.
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

class Response {
	/**
     * List of middleware that will run on every response.
	 * 
	 * This is ran after matched route completed running.
     *
     * @var array<int, class-string|string>
     */
	protected static $middleware = Array(
		// Define your response middleware here.
	);

	/**
	 * Ran on every request, before routing happend.
	 * 
	 * @param	\Blink\Request	$request	The request.
	 * @param	\Blink\Response	$response	The intercepted response.
	 * @return	\Blink\Response	Return back the response.
	 */
	public static function handle(\Blink\Request $request, \Blink\Response $response): \Blink\Response {
		
		foreach (static::$middleware as $middleware) {
			if (!class_exists($middleware))
				throw new ClassNotFound($middleware);

			$response = $middleware::handle($request, $response);

			if (!$middleware instanceof \Blink\Response)
				throw new InvalidMiddlewareReturn($response, \Blink\Response::class, get_class($response));
		}

		return $response;
	}
}
