<?php

namespace Blink\Ether\ErrorPage;

/**
 * Contract for middleware that runs while an error page is being built.
 *
 * Dispatched after the core contexts have been assembled, so an implementation
 * can add groups, change the ones already there, or attach diagnostics of its
 * own through the supplied {@see ErrorPageContext}.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
interface ErrorPageMiddleware {
	/**
	 * @param	ErrorPageContext					$context
	 * @param	callable(ErrorPageContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(ErrorPageContext $context, callable $next): mixed;
}
