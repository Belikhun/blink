<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * MissingParam.php
 *
 * Exception for missing request param.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class MissingParam extends BaseException {
	public function __construct(String $param) {
		parent::__construct(MISSING_PARAM, "Missing required param: $param", 400, Array( "param" => $param ));
	}
}
