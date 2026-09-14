<?php

namespace Blink\Lightrail\Attribute;

use Attribute;

/**
 * Attach handler middleware to an observer method. Takes class names, aliases
 * and group names, like every other Ether reference.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Middleware {
	/** @var string[] */
	public array $middleware;

	public function __construct(string ...$middleware) {
		$this -> middleware = $middleware;
	}
}
