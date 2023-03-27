<?php

/**
 * Route.php
 * 
 * Exceptions used in file operations.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;
use Blink\Exception\BaseException;

class FileNotFound extends BaseException {
	public function __construct(String $path) {
		parent::__construct(
			FILE_MISSING,
			"File \"$path\" does not exist on this server.",
			404,
			Array( "path" => $path )
		);
	}
}

class FileInstanceNotFound extends BaseException {
	public function __construct(String $hash) {
		parent::__construct(
			FILE_INSTANCE_NOT_FOUND,
			"Không tồn tại tệp với mã $hash trong cơ sở dữ liệu!",
			404
		);
	}
}

class FileReadError extends BaseException {
	public function __construct(String $file, int $tries = 1) {
		parent::__construct(
			FILE_READ_ERROR,
			"Không thể đọc file \"{$file}\"" . ($tries > 1 ? " sau {$tries} lần thử" : ""),
			500
		);
	}
}

class FileWriteError extends BaseException {
	public function __construct(String $file, int $tries = 1) {
		parent::__construct(
			FILE_WRITE_ERROR,
			"Không thể ghi vào file \"{$file}\"" . ($tries > 1 ? " sau {$tries} lần thử" : ""),
			500
		);
	}
}
