<?php

namespace Blink\Http\Router\Middleware;

use Blink\Ether\Route\RouteContext;
use Blink\Ether\Route\RouteMiddleware;
use Blink\Exception\RequestDenied;
use Blink\Exception\UnsupportedAuthScheme;
use Blink\Session;

/**
 * Start the session and authenticate the request against it.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class AuthenticateUser implements RouteMiddleware {
	/**
	 * @param	RouteContext					$context
	 * @param	callable(RouteContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(RouteContext $context, callable $next): mixed {
		$request = $context -> request;
		$context -> isCrossSite = ($request -> header("Sec-Fetch-Site") === "cross-site");

		$auth = $request -> header("Authorization");
		$scheme = null;
		$token = null;

		if (!empty($auth)) {
			$parts = explode(" ", trim($auth), 2);

			// A bare value with no scheme reads as Basic, which is what blink
			// has always accepted from clients that send only the token.
			list($scheme, $token) = (count($parts) === 2)
				? $parts
				: array( "Basic", $parts[0] );
		}

		if (empty($token)) {
			Session::start();
		} else {
			switch ($scheme) {
				case "Basic":
					Session::start();
					Session::token($token);
					break;

				case "Session":
					Session::start($token);
					break;

				default:
					throw new UnsupportedAuthScheme($scheme);
			}

			$context -> authenticatedByHeader = true;
		}

		// A cookie session travelling to another origin is a request the user
		// never meant to make, so a cross-site caller has to carry its own
		// credentials in the header.
		if ($context -> isCrossSite && !$context -> authenticatedByHeader && Session::loggedIn()) {
			throw new RequestDenied(
				"Authentication must be made via <code>Authorization</code> header when request is in cross-site mode"
			);
		}

		return $next($context);
	}
}
