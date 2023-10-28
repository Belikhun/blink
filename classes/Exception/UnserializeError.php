<?php

namespace Blink\Exception;

/**
 * UnserializeError.php
 * 
 * Corrupted serialized data.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class UnserializeError extends BaseException {
	public function __construct(String $file, String $message, $data) {
		$file = getRelativePath($file);
		$message = str_replace("unserialize(): ", "", $message);

		parent::__construct(47, "unserialize(\"{$file}\"): {$message}", 500, Array( "file" => $file, "data" => $data ));
	}
}
