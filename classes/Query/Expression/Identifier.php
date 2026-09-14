<?php

namespace Blink\Query\Expression;

use Blink\Exception\CodingError;

/**
 * Quoting for a table, column or alias name.
 *
 * One place that decides how an identifier is written, and the one place that
 * checks it looks like an identifier at all. Anything reaching SQL unquoted is
 * a place where a caller-supplied name could change the statement's meaning.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Identifier {
	/**
	 * Backticks for both engines blink ships: MySQL's own quoting, which
	 * SQLite also accepts.
	 */
	public static function quote(string $identifier): string {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier))
			throw new CodingError("Invalid SQL identifier <code>{$identifier}</code>");

		return "`{$identifier}`";
	}

	public static function qualify(string $left, string $right): string {
		return static::quote($left) . "." . static::quote($right);
	}
}
