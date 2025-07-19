<?php

namespace Blink\Exception;

/**
 * Class representing an access denied error.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class AccessDenied extends BaseException {
	public function __construct(string $message, string $details = null) {
		parent::__construct(ACCESS_DENIED, $message, 403, details: $details);
	}
}
