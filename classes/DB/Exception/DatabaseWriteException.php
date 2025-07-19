<?php

namespace Blink\DB\Exception;

/**
 * DML database write exception class.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseWriteException extends DatabaseException {
	public function __construct(string $details = null, string $sql = null, array $params = null) {
		parent::__construct(
			"Error writing to database!",
			details: $details,
			sql: $sql,
			params: $params
		);
	}
}
