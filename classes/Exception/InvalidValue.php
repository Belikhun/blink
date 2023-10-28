<?php

namespace Blink\Exception;

/**
 * InvalidValue.php
 * 
 * Invalid Value.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidValue extends BaseException {
	public function __construct(String $value, String $type) {
		parent::__construct(
			INVALID_VALUE,
			"The value <code>{$value}</code> is not a valid <code>{$type}</code>!",
			400
		);
	}
}
