<?php

namespace Blink\Exception;

/**
 * Exception when an expired token is used in authorization.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TokenExpired extends BaseException {
	public function __construct() {
		parent::__construct(
			TOKEN_EXPIRED,
			"Your authorization token has expired",
			403
		);
	}
}
