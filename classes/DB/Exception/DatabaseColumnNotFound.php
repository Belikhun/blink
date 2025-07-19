<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

/**
 * Exception when column is not found.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseColumnNotFound extends BaseException {
	public function __construct(string $column, ?string $table = null, ?string $details = null) {
		$message = "Column name <code>{$column}</code> does not exist";

		if (!empty($table))
			$message .= " in table <code>{$table}</code>";

		parent::__construct(
			SQL_ERROR,
			$message,
			500,
			array(
				"column" => $column,
				"table" => $table
			),
			$details
		);
	}
}
