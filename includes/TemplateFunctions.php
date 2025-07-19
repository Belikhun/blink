<?php

namespace Blink\Template;

use Blink\Exception\TemplateIllegalCall;

/**
 * Functions used in template.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

class Functions {
	public static ?array $context = null;

	protected static function checkContext() {
		if (static::$context === null)
			throw new TemplateIllegalCall();
	}

	/**
	 * Print out the value specified by name, with html specical
	 * characters encoded to prevent XSS attack.
	 * 
	 * @param	mixed	$value
	 */
	public static function val(string $name, $default = null) {
		static::checkContext();

		if (!isset(static::$context[$name])) {
			static::v($default);
			return;
		}

		static::v(static::$context[$name]);
	}

	/**
	 * Print out the value specified by name, without encoding html
	 * specical characters.
	 * 
	 * @param	mixed	$value
	 */
	public static function raw(string $name, $default = null) {
		static::checkContext();

		if (!isset(static::$context[$name])) {
			static::r($default);
			return;
		}

		static::r(static::$context[$name]);
	}

	/**
	 * Print out the value, with html specical characters encoded
	 * to prevent XSS attack.
	 * 
	 * @param	mixed	$value
	 */
	public static function v($value) {
		echo htmlspecialchars($value);
	}

	/**
	 * Print out the value, without encoding html specical characters.
	 * 
	 * @param	mixed	$value
	 */
	public static function r($value) {
		echo $value;
	}
}
