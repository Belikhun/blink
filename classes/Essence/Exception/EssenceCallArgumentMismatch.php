<?php

namespace Blink\Essence\Exception;

use Blink\Exception\BaseException;

/**
 * Exception thrown when an argument passed to a callable does not match the
 * type its signature asks for.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EssenceCallArgumentMismatch extends BaseException {
	public function __construct(string $message) {
		parent::__construct(
			ESSENCE_CALL_ARGUMENT_MISMATCH,
			$message,
			500
		);
	}
}
