<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * FileReadError.php
 * 
 * Exception used when an error occured in reading operation.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileReadError extends BaseException {
	public function __construct(String $file, int $tries = 1) {
		parent::__construct(
			FILE_READ_ERROR,
			"Không thể đọc file \"{$file}\"" . ($tries > 1 ? " sau {$tries} lần thử" : ""),
			500
		);
	}
}
