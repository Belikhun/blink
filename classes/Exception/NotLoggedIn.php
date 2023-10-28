<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * NotLoggedIn.php
 *
 * Exception indicate that current session does not come with an authorized user.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class NotLoggedIn extends BaseException {
	public function __construct() {
		parent::__construct(
			NOT_LOGGED_IN,
			"You are not logged in!",
			400
		);
	}
}
