<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * Exceptions used in file operations.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileNotFound extends BaseException {
	public function __construct(string $path) {
		parent::__construct(
			FILE_MISSING,
			"File \"$path\" does not exist on this server.",
			404,
			array( "path" => $path )
		);
	}
}
