<?php

namespace Blink\Lightrail\Attribute;

use Attribute;

/**
 * Mark an observer method as handling an event.
 *
 * ```php
 * #[Observe(ModelSaved::class)]
 * public static function onSaved(ModelSaved $event): void { ... }
 * ```
 *
 * Repeatable, so one method can answer to several events.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Observe {
	public function __construct(public string $eventClass) {}
}
