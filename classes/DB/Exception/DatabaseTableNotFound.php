<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

/**
 * Exception thrown when table is not found.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseTableNotFound extends BaseException {
	public function __construct(string $table, ?string $details = null) {
		parent::__construct(
			SQL_ERROR,
			"Definition for table <code>{$table}</code> does not exist in database nor is defined in query alias directory!",
			500,
			array(
				"table" => $table
			),
			$details
		);
	}
}
