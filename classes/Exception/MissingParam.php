<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * Exception thown when missing a required request parameter.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class MissingParam extends BaseException {
	public function __construct(string $param) {
		parent::__construct(MISSING_PARAM, "Missing required parameter: $param", 400, array( "param" => $param ));
	}
}
