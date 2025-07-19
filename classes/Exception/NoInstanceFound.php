<?php

namespace Blink\Exception;

/**
 * Exception thrown when a required instance is not found.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class NoInstanceFound extends BaseException {
	public function __construct(string $name) {
		$data = array(
			"class" => $name,
			"name" => $name::modelName()
		);

		parent::__construct(
			NO_INSTANCE,
			"Model <code>{$name}</code> (<code>" . $data["class"] . "</code>) does not have any instance in the database or you don't have permission to access any of them!",
			404,
			$data
		);
	}
}
