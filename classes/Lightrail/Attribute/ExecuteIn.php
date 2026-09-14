<?php

namespace Blink\Lightrail\Attribute;

use Attribute;
use Blink\Lightrail\ExecutionMode;

/**
 * Say when a handler should run: immediately, at shutdown, on a runner, or
 * batched. See {@see ExecutionMode}.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ExecuteIn {
	public string $mode;

	public function __construct(string $mode = ExecutionMode::IMMEDIATE) {
		$this -> mode = ExecutionMode::normalize($mode);
	}
}
