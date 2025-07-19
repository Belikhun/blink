<?php

namespace Blink\DB\Exception;

/**
 * DML exception for receiving multiple records when only one expected.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseMultipleRecordsException extends DatabaseException {
	public function __construct(int $count, string $sql = null, array $params = null) {
		parent::__construct(
			"Multiple records received in <code>record()</code> call!",
			"Expected to only receive one record, got {$count} instead",
			sql: $sql,
			params: $params
		);
	}
}
