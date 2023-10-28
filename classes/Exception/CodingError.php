<?php

namespace Blink\Exception;

/**
 * CodingError.php
 * 
 * Class representing a coding error, which need to be fixed by developer.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CodingError extends BaseException {
	public function __construct($message, Array $data = null) {
		parent::__construct(CODING_ERROR, $message, 500, $data);
	}
}
