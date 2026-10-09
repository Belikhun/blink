<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

/**
 * A database instance was asked for by a name nothing registered.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseNotRegistered extends BaseException {
	public function __construct(string $name, ?string $details = null) {
		parent::__construct(
			DATABASE_NOT_REGISTERED,
			"No database instance is registered as <code>{$name}</code>.",
			500,
			array( "name" => $name ),
			$details ?? "Declare it in <code>CONFIG::\$DATABASES</code> or register it with <code>Database::register()</code>."
		);
	}
}
