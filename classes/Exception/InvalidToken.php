<?php

namespace Blink\Exception;

/**
 * Exception when an invalid token is used in authorization.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidToken extends BaseException {
	public function __construct() {
		parent::__construct(
			INVALID_TOKEN,
			"Invalid external authorization token",
			403
		);
	}
}
