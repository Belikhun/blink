<?php

namespace Blink\Ether\Response;

/**
 * Contract for middleware that runs after a response has been created.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
interface ResponseMiddleware {
	/**
	 * @param	ResponseContext						$context
	 * @param	callable(ResponseContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(ResponseContext $context, callable $next): mixed;
}
