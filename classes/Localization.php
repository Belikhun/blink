<?php

namespace Blink;

use Blink\Exception\StringNotFound;

/**
 * Localization & string APIs.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Localization {
	const BASE_LANG = "en";

	public static string $currentLanguage;

	public static array $store = [];

	/**
	 * Load strings into store.
	 * This will load all `{lang}.php` files in languages folder specified in {@see CONFIG::$LANGS}
	 *
	 * @param	string		$lang		Language to load, example `en`, `vi`
	 * @param	bool		$reload		Force reloading strings even if it's loaded
	 * @return	bool
	 */
	public static function load(string $lang, bool $reload = false) {
		$lang = strtolower($lang);

		if (isset(static::$store[$lang]) && !$reload)
			return true;

		$loaded = [];

		foreach (\CONFIG::$LANGS as $langDir) {
			$filePath = "{$langDir}/{$lang}.php";

			if (!file_exists($filePath))
				continue;

			$strings = require $filePath;
			$loaded = array_merge($loaded, $strings);
		}

		static::$store[$lang] = $loaded;
		return true;
	}

	/**
	 * Return string content of given string identifier.
	 *
	 * @param	string	$identifier		String identifier.
	 * @param	array	$placeholders	Placeholder values to be replaced inside string. String placeholders are capsuled between `{}`. Example: `{name}`
	 * @param	string	$lang			Override language to return, default to current set language.
	 * @return	string
	 */
	public static function string(
		string $identifier,
		array $placeholders = [],
		string $lang = null,
		bool $strict = false
	): string {
		$lang = $lang ?? static::$currentLanguage;
		$string = null;

		if (isset(static::$store[$lang]) && isset(static::$store[$lang][$identifier]))
			$string = static::$store[$lang][$identifier];

		if ($string === null && isset(static::$store[static::BASE_LANG][$identifier]))
			$string = static::$store[static::BASE_LANG][$identifier];

		if ($strict && $string === null)
			throw new StringNotFound($identifier);

		if ($string === null)
			return "[{$identifier}]";

		if (!empty($placeholders)) {
			foreach ($placeholders as $name => $value)
				$string = str_replace("{{$name}}", $value, $string);
		}

		return $string;
	}

	public static function setup() {
		static::load(static::BASE_LANG);
	}
}
