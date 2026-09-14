<?php

namespace Blink\Ether\Route;

/**
 * Contract for middleware that runs before a route has been matched.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
interface RouteMiddleware {
	/**
	 * @param	RouteContext					$context
	 * @param	callable(RouteContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(RouteContext $context, callable $next): mixed;
}
