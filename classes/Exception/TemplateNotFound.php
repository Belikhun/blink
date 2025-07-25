<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * Exception used when requesting a missing template.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TemplateNotFound extends BaseException {
	public function __construct(string $name) {
		parent::__construct(
			TEMPLATE_NOT_FOUND,
			"Template \"{$name}\" does not exist!",
			500,
			array( "name" => $name )
		);
	}
}
