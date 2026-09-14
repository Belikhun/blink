<?php

namespace Blink\Console\Exception;

use Blink\Exception\BaseException;

/**
 * The arguments or options a command was given do not match its usage line.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidInput extends BaseException {
	public function __construct(string $message, string $details = null) {
		parent::__construct(
			CONSOLE_INVALID_INPUT,
			$message,
			400,
			null,
			$details
		);
	}
}
