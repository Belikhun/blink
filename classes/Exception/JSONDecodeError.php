<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * JSONDecodeError.php
 * 
 * Exception indicate that there was an error parsing json data.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class JSONDecodeError extends BaseException {
	public function __construct(String $file, String $message, $data) {
		$file = getRelativePath($file);
		parent::__construct(INVALID_JSON, "json_decode({$file}): {$message}", 500, Array( "file" => $file, "data" => $data ));
	}
}
