<?php

namespace Blink\Http\Router\Middleware;

use Blink\Ether\Route\RouteContext;
use Blink\Ether\Route\RouteMiddleware;
use Blink\Localization;

/**
 * Apply the language and timezone the request asks for.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class SetupSessionEnvironment implements RouteMiddleware {
	/**
	 * @param	RouteContext					$context
	 * @param	callable(RouteContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(RouteContext $context, callable $next): mixed {
		$request = $context -> request;

		if ($lang = $request -> header("Content-Language"))
			Localization::setLanguage($lang);

		$timezone = $request -> header("Set-Timezone");

		// Checked against the zone list rather than passed straight through:
		// the value comes from the client, and an unknown zone would raise a
		// warning that the error handler turns into a 500.
		if (!empty($timezone) && in_array($timezone, timezone_identifiers_list(), true))
			date_default_timezone_set($timezone);

		return $next($context);
	}
}
