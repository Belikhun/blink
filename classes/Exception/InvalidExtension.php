<?php

namespace Blink\Exception;

/**
 * Exception thrown when file extension is invalid/or not accepted.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidExtension extends BaseException {
	public function __construct(string $extension, array $accepts = [], array $rejects = []) {
		$message = "File extension <code>{$extension}</code> is invalid or is blacklisted";
		$details = [];

		if (!empty($accepts))
			$details[] = "Only file with extension <code>" . implode(", ", $accepts) . "</code> are accepted.";

		if (!empty($rejects))
			$details[] = "All files with extension <code>" . implode(", ", $rejects) . "</code> are rejected.";

		parent::__construct(
			INVALID_EXTENSION,
			$message,
			400,
			array(
				"extension" => $extension,
				"accepts" => $accepts,
				"rejects" => $rejects
			),
			!empty($details) ? implode("\n", $details) : null
		);
	}
}
