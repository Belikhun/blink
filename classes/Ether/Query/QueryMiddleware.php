<?php

namespace Blink\Ether\Query;

/**
 * Contract for middleware that runs around a query's terminal operation.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
interface QueryMiddleware {
	/**
	 * @param	QueryContext					$context
	 * @param	callable(QueryContext): mixed	$next
	 * @return	mixed
	 */
	public function handle(QueryContext $context, callable $next): mixed;
}
