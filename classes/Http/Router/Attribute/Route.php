<?php

namespace Blink\Http\Router\Attribute;

use Attribute;

/**
 * The routing path attribute, designed to be used in
 * a controller.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

#[Attribute(Attribute::TARGET_METHOD)]
class Route {
	/**
	 * Mark this URI to route to the following controller method.
	 *
	 * @param	string	$uri	The rouing URI
	 */
	public function __construct(string $uri) {}
}
