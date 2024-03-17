<?php

namespace Blink\Router\Attribute;

use Attribute;

/**
 * The Http Methods routing attribute, designed to be used in
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
class HttpMethod {
	const VERB = "";

	/**
	 * Mark the accepted http request method(s) for this controller method.
	 */
	public function __construct(String ...$method) {}
}
