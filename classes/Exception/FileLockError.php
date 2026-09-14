<?php

namespace Blink\Exception;

use function Blink\getRelativePath;

/**
 * A lock file could not be opened or locked.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileLockError extends BaseException {
	public string $path;

	public function __construct(string $path, string $reason) {
		$this -> path = $path;
		$relative = getRelativePath($path);

		parent::__construct(
			FILE_LOCK_ERROR,
			"Lock file <code>{$relative}</code> {$reason}.",
			500,
			array( "path" => $path, "reason" => $reason ),
			"Check that the data directory is writable by the web server user."
		);
	}
}
