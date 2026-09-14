<?php

namespace Blink\Lightrail\Attribute;

use Attribute;

/**
 * Opt an observer method out of handler middleware that would otherwise run.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class WithoutMiddleware {
	/** @var string[] */
	public array $middleware;

	public function __construct(string ...$middleware) {
		$this -> middleware = $middleware;
	}
}
