<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * WrongPassword.php
 * 
 * Exception indicate that an incorrect password was used to authenticate client.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class WrongPassword extends BaseException {
	public function __construct(string $username) {
		parent::__construct(
			ACCESS_DENIED,
			"Wrong password supplied to login into \"{$username}\"",
			403
		);
	}
}
