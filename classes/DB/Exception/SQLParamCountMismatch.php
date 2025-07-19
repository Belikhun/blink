<?php

namespace Blink\DB\Exception;

/**
 * SQL exception for params count mismatch.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class SQLParamCountMismatch extends DatabaseException {
	public function __construct(int $expected, int $supplied, string $sql = null, array $params = null) {
		parent::__construct(
			"SQL query exprected to get {$expected} query parameters, got {$supplied} instead!",
			sql: $sql,
			params: $params
		);
	}
}
