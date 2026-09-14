<?php

namespace Blink\Http\Router\Attribute;

use Attribute;

/**
 * Attach router lifecycle middleware to a controller method.
 *
 * Takes route, request and response references alike: class names, aliases and
 * group names.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware {
	public function __construct(string ...$middleware) {}
}
