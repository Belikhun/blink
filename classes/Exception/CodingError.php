<?php

namespace Blink\Exception;

/**
 * Class representing a runtime error, caught by
 * {@link set_error_handler()}.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CodingError extends BaseException {
	public function __construct(string $message, string $details = null) {
		parent::__construct(CODING_ERROR, $message, 500, details: $details);
	}
}
