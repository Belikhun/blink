<?php

namespace Blink\Exception;

/**
 * NoInstanceFound.php
 *
 * Exception indicate that model instance is empty in database table.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class NoInstanceFound extends BaseException {
	public function __construct(String $name) {
		$display = $name::modelName();

		parent::__construct(
			12,
			"Model <code>{$display}</code> (<code>{$name}</code>) does not have any instance in the database or you don't have permission to access any of them!",
			404,
			Array(
				"class" => $name,
				"name" => $name::modelName()
			)
		);
	}
}
