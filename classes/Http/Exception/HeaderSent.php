<?php

namespace Blink\Http\Exception;

use Blink\Exception\BaseException;

/**
 * Exception raised when trying to send header after
 * all headers has been sent to the client.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class HeaderSent extends BaseException {
	public function __construct(string $file, int $line) {
		$file = \Blink\getRelativePath($file);

		parent::__construct(
			HEADER_SENT,
			"Header already sent at \"{$file}:{$line}\"",
			500,
			array( "file" => $file, "line" => $line )
		);
	}
}
