<?php

namespace Blink\Ether\Event;

/**
 * Contract for event dispatch middleware.
 *
 * Runs once per Lightrail emission, wrapping handler resolution and dispatch.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
interface EventMiddleware {
	/**
	 * @param	EventDispatchContext					$context
	 * @param	callable(EventDispatchContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(EventDispatchContext $context, callable $next): mixed;
}
