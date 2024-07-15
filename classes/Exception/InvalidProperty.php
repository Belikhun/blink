<?php
/**
 * InvalidProperty.php
 *
 * Exception when accessing unknown/invalid property of a model.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;

class InvalidProperty extends BaseException {
	public function __construct(string $name, string $class) {
		parent::__construct(
			INVALID_PROPERTY,
			"Undefined property <code>{$name}</code> in Model <code>{$class}</code>",
			500,
			array(
				"name" => $name,
				"class" => $class
			)
		);
	}
}
