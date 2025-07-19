<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

/**
 * Exception when column name is invalid.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseInvalidColumn extends BaseException {
	public function __construct(string $column, ?string $details = null) {
		parent::__construct(
			SQL_ERROR,
			"Column name <code>{$column}</code> is invalid or contain illegal characters",
			500,
			array(
				"column" => $column
			),
			$details
		);
	}
}
