<?php

namespace Blink\Lightrail\Attribute;

use Attribute;

/**
 * Order a handler against the others watching the same event. Higher runs
 * first; handlers sharing a priority keep registration order.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Priority {
	public function __construct(public int $priority) {}
}
