<?php

namespace Blink\Exception;

/**
 * Exception thrown when processing an invalid value.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidValue extends BaseException {
	public function __construct(string $name, string $value, ?string $details = null) {
		parent::__construct(
			INVALID_VALUE,
			"Invalid value <code>{$value}</code> for property/param/argument <code>{$name}</code>",
			403,
			array(
				"name" => $name,
				"value" => $value
			),
			$details
		);
	}
}
