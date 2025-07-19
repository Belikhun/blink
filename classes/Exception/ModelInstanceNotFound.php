<?php

namespace Blink\Exception;

/**
 * Exception used when a model instance does not exist in the database.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ModelInstanceNotFound extends BaseException {
	public function __construct(string $name, $id) {
		parent::__construct(
			NO_INSTANCE,
			"Model <code>{$name}</code> does not have instance ID of <code>{$id}</code> in the database!",
			404,
			array( "name" => $name, "id" => $id )
		);
	}
}
