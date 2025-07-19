<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * Exceptions thrown when specified uploaded file is not found in the request.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RequestFileMissing extends BaseException {
	public function __construct(string $name) {
		parent::__construct(
			FILE_MISSING,
			"No file with name <code>{$name}</code> found in the body of this request",
			400,
			array( "name" => $name )
		);
	}
}
