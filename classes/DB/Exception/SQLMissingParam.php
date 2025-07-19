<?php

namespace Blink\DB\Exception;

/**
 * SQL exception for missing named param.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class SQLMissingParam extends DatabaseException {
	public function __construct(string $name, string $sql = null, array $params = null) {
		parent::__construct(
			"Missing SQL query named parameter <code>{$name}</code>",
			sql: $sql,
			params: $params
		);
	}
}
