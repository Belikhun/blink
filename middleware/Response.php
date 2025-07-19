<?php

namespace Blink\Middleware;

use Blink\Exception\ClassNotFound;
use Blink\Middleware\Exception\InvalidMiddlewareReturn;
use function Blink\stringify;

/**
 * Response middleware.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Response {
	/**
     * List of middleware that will run on every response.
	 * 
	 * This is ran after matched route completed running.
     *
     * @var array<int, class-string|string>
     */
	protected static $middleware = array(
		// Define your response middleware here.
	);

	/**
	 * Ran on every request, before routing happend.
	 * 
	 * @param	\Blink\Http\Request		$request	The request.
	 * @param	\Blink\Http\Response	$response	The intercepted response.
	 * @return	\Blink\Http\Response	Return back the response.
	 */
	public static function handle(\Blink\Http\Request $request, \Blink\Http\Response $response): \Blink\Http\Response {
		
		foreach (static::$middleware as $middleware) {
			if (!class_exists($middleware))
				throw new ClassNotFound($middleware);

			$handled = $middleware::handle($request, $response);

			if ($handled === null) {
				// Does not return anything, fallback to current response data.
				$handled = $response;
			} else {
				$response = $handled;
			}

			if (!($response instanceof \Blink\Http\Response))
				throw new InvalidMiddlewareReturn($middleware, \Blink\Http\Response::class, stringify($response));
		}

		return $response;
	}
}
