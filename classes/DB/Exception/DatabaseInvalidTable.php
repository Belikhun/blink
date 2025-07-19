<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

/**
 * Exception when table name is invalid.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseInvalidTable extends BaseException {
	public function __construct(string $table, ?string $details = null) {
		parent::__construct(
			SQL_ERROR,
			"Table name <code>{$table}</code> is invalid or contain illegal characters",
			500,
			array(
				"table" => $table
			),
			$details
		);
	}
}
