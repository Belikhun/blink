<?php

namespace Blink\Ether\Handler;

/**
 * Contract for handler invocation middleware.
 *
 * Runs around each individual Lightrail handler call, in every execution mode.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
interface HandlerMiddleware {
	/**
	 * @param	HandlerInvocationContext					$context
	 * @param	callable(HandlerInvocationContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(HandlerInvocationContext $context, callable $next): mixed;
}
