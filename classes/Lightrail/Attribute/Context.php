<?php

namespace Blink\Lightrail\Attribute;

use Attribute;

/**
 * Narrow a handler to events whose context matches.
 *
 * Every key given has to be present with the same value, so a handler can
 * watch one kind of a broadly-emitted event without checking inside itself:
 *
 * ```php
 * #[Observe(ModelSaved::class)]
 * #[Context(["model" => Invoice::class])]
 * public static function onInvoiceSaved(ModelSaved $event): void { ... }
 * ```
 *
 * A list of values means any of them, and a keyed array is a nested context
 * matched the same way:
 *
 * ```php
 * #[Context(["model" => [Invoice::class, Receipt::class]])]
 * ```
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Context {
	public function __construct(public array $context) {}
}
