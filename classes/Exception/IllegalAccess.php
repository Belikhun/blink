<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * Exceptions indicate that client is performing an illegal request.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class IllegalAccess extends BaseException {
	public function __construct(string $message = null) {
		parent::__construct(
			ACCESS_DENIED,
			$message ?: "You don't have permission to access this resource.",
			403
		);
	}
}
