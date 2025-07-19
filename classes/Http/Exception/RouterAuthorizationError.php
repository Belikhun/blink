<?php

namespace Blink\Http\Exception;

use Blink\Exception\BaseException;

/**
 * Exception occured when authenticating during routing.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouterAuthorizationError extends BaseException {
	public function __construct(string $message, ?string $details = null) {
		parent::__construct(
			ROUTER_AUTH_ERROR,
			$message,
			400,
			details: $details
		);
	}
}
