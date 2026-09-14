<?php

namespace Blink\Essence\Exception;

use Blink\Exception\BaseException;

/**
 * Exception thrown when Essence detects a circular constructor dependency.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EssenceCircularDependency extends BaseException {
	public function __construct(array $stack) {
		$cycle = implode(" -> ", $stack);

		parent::__construct(
			ESSENCE_CIRCULAR_DEPENDENCY,
			"Circular Essence dependency detected: <code>{$cycle}</code>.",
			500,
			[ "stack" => $stack ]
		);
	}
}
