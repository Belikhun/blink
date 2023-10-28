<?php

namespace Blink\Exception;

/**
 * TokenExpired.php
 * 
 * The supplied token has been expired.
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
		parent::__construct(TOKEN_EXPIRED, "The token supplied is expired.", 403);
	}
}
