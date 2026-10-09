<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

/**
 * A query reached across database instances.
 *
 * One statement runs on one connection, so every table, join and subquery
 * in it has to live on the same instance.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseMismatch extends BaseException {
	public function __construct(string $expected, string $actual, ?string $details = null) {
		parent::__construct(
			DATABASE_MISMATCH,
			"This query runs on database instance <code>{$expected}</code>, but was given something from <code>{$actual}</code>.",
			500,
			array( "expected" => $expected, "actual" => $actual ),
			$details
		);
	}
}
