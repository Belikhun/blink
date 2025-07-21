<?php

use Blink\Localization;

/**
 * Define global variables / functions.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

/**
 * Return string content of given string identifier.
 *
 * @param	string	$identifier		String identifier.
 * @param	array	$placeholders	Placeholder values to be replaced inside string. String placeholders are capsuled between `{}`. Example: `{name}`
 * @param	string	$lang			Override language to return, default to current set language.
 * @return	string
 */
function __(
	string $identifier,
	array $placeholders = [],
	string $lang = null,
	bool $strict = false
): string {
	return Localization::string($identifier, $placeholders, $lang, $strict);
}
