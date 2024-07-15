<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * FileInstanceNotFound.php
 * 
 * Exception used when file instance does not exist in the database with specified hash.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileInstanceNotFound extends BaseException {
	public function __construct(string $hash) {
		parent::__construct(
			FILE_INSTANCE_NOT_FOUND,
			"File does not exist with hash <code>{$hash}</code> in the database!",
			404
		);
	}
}
