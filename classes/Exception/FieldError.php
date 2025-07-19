<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * Field error exception
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FieldError extends BaseException {
	public function __construct(string $name, string $reason) {
		parent::__construct(
			FIELD_DATA_ERROR,
			"Field <code>{$name}</code> contains invalid data!",
			400,
			array(
				"name" => $name,
				"reason" => $reason
			),
			$reason
		);
	}
}
