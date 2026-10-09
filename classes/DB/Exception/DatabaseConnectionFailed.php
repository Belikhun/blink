<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

/**
 * A database instance could not be connected.
 *
 * Raised in place of the driver's own error so the message names the
 * instance, which is what tells a failing archive database apart from the
 * site's own.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseConnectionFailed extends BaseException {
	public function __construct(string $name, \Throwable $previous) {
		parent::__construct(
			DATABASE_CONNECTION_FAILED,
			"Could not connect database instance <code>{$name}</code>: " . $previous -> getMessage(),
			500,
			array( "name" => $name ),
			get_class($previous)
		);

		$this -> file = $previous -> getFile();
		$this -> line = $previous -> getLine();
	}
}
