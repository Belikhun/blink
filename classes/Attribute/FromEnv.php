<?php

namespace Blink\Attribute;

/**
 * Bind a config setting to an environment variable.
 *
 * ```php
 * #[FromEnv("DB_HOST")]
 * public static string $DB_HOST = "127.0.0.1";
 * ```
 *
 * Only a setting carrying this attribute is read from the environment, and the
 * variable is named here rather than guessed from the property. Matching by
 * name alone would hand `LANG`, which nearly every Linux shell exports, to
 * whatever setting happened to share the spelling.
 *
 * The value is converted to the property's declared type, so an environment
 * that can only carry strings still fills an `int` or a `bool` correctly.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class FromEnv {
	/**
	 * @param	string	$name	The environment variable to read.
	 */
	public function __construct(public string $name) {}
}
