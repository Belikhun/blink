<?php

namespace Blink\Router\Attribute;

use Attribute;

/**
 * The Http HEAD routing attribute, designed to be used in
 * a controller.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

#[Attribute(Attribute::TARGET_METHOD)]
class HttpHead extends HttpMethod {
	const VERB = "HEAD";
}
