<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * FileWriteError.php
 * 
 * Exception used when an error occured in writing operation.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileWriteError extends BaseException {
	public function __construct(String $file, int $tries = 1) {
		parent::__construct(
			FILE_WRITE_ERROR,
			"Không thể ghi vào file \"{$file}\"" . ($tries > 1 ? " sau {$tries} lần thử" : ""),
			500
		);
	}
}
