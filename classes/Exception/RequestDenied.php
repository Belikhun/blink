<?php


namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * Exceptions used when incoming request is being denied.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RequestDenied extends BaseException {
	public function __construct(string $description, ?string $details = null) {
		parent::__construct(
			ACCESS_DENIED,
			$description,
			400,
			null,
			$details
		);
	}
}
