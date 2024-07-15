<?php

namespace Blink\Exception;

/**
 * ModelInstanceNotFound.php
 *
 * Exception indicate that the model instance does not exist in database.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ModelInstanceNotFound extends BaseException {
	public function __construct(string $name, $id) {
		$display = $name::modelName();

		parent::__construct(
			12,
			"Model <code>{$display}</code> (<code>{$name}</code>) does not have instance ID of [{$id}] in the database or you don't have permission to access it!",
			404,
			array(
				"class" => $name,
				"name" => $display,
				"id" => $id
			)
		);
	}
}
