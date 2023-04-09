<?php
/**
 * HeaderSent.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;

class HeaderSent extends BaseException {
	public function __construct(String $file, int $line) {
		$file = getRelativePath($file);

		parent::__construct(
			HEADER_SENT,
			"Header already sent at \"{$file}:{$line}\"",
			500,
			Array( "file" => $file, "line" => $line )
		);
	}
}
