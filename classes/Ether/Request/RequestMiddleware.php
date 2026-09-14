<?php

namespace Blink\Ether\Request;

/**
 * Contract for middleware that runs around a matched route's handler.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
interface RequestMiddleware {
	/**
	 * @param	RequestContext					$context
	 * @param	callable(RequestContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(RequestContext $context, callable $next): mixed;
}
