<?php

namespace Blink\Http\Router\Middleware;

use Blink\Ether\Response\ResponseContext;
use Blink\Ether\Response\ResponseMiddleware;
use Blink\Http\Response;
use Blink\Http\Router;

/**
 * Apply CORS headers for cross-site, header-authenticated and OPTIONS requests.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ApplyCorsHeaders implements ResponseMiddleware {
	/**
	 * @param	ResponseContext						$context
	 * @param	callable(ResponseContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(ResponseContext $context, callable $next): mixed {
		$result = $next($context);

		// A request that carried its own credentials in the header cannot be
		// made by a browser on someone else's behalf, so widening the origin
		// for it costs nothing.
		if ($context -> isOptionRequest || $context -> isCrossSite || $context -> authenticatedByHeader) {
			$origin = $context -> request -> header("Origin");
			$context -> response -> header("Access-Control-Allow-Origin", $origin ?: "*");
			$context -> response -> header("Access-Control-Allow-Methods", implode(", ", array_merge(Router::$verbs, [ "OPTIONS" ])));
			$context -> response -> header("Access-Control-Allow-Headers", "*");
		}

		return ($result instanceof Response)
			? $result
			: $context -> response;
	}
}
