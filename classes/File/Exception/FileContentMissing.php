<?php

namespace Blink\File\Exception;

use Blink\Exception\BaseException;

/**
 * The row is there, the bytes are not.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileContentMissing extends BaseException {
	public string $hash;

	public function __construct(string $hash, ?int $id = null) {
		$this -> hash = $hash;

		parent::__construct(
			FILE_CONTENT_MISSING,
			"The file store holds no content for <code>{$hash}</code>.",
			404,
			array( "hash" => $hash, "id" => $id ),
			"The record is there, so the content was removed from the store without its row going with it. <code>file:prune</code> lists both halves of that."
		);
	}
}
