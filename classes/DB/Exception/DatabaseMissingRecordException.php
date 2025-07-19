<?php

namespace Blink\DB\Exception;

/**
 * DML exception for missing required database record.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseMissingRecordException extends DatabaseException {
	public function __construct(string $sql = null, array $params = null) {
		parent::__construct(
			"Missing required database record!",
			sql: $sql,
			params: $params
		);
	}
}
