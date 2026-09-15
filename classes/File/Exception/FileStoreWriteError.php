<?php

namespace Blink\File\Exception;

use Blink\Exception\BaseException;

/**
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileStoreWriteError extends BaseException {
	public string $hash;

	public function __construct(string $hash, string $reason) {
		$this -> hash = $hash;

		parent::__construct(
			FILE_WRITE_ERROR,
			"The file store could not take content <code>{$hash}</code>.",
			500,
			array( "hash" => $hash, "reason" => $reason ),
			$reason
		);
	}
}
