<?php

namespace Blink\Exception;

use function Blink\getRelativePath;

/**
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
	public function __construct(string $file, string $message, $data) {
		$file = getRelativePath($file);
		$message = str_replace("unserialize(): ", "", $message);

		parent::__construct(47, "unserialize(\"{$file}\"): {$message}", 500, array( "file" => $file, "data" => $data ));
	}
}
