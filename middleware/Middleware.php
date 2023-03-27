<?php
/**
 * Middleware.php
 * 
 * Base Middleware class.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;

class Middleware {
	protected static bool $disabled = false;

	public static function disable() {
		static::$disabled = true;
	}

	public static function enable() {
		static::$disabled = false;
	}

	public static function disabled() {
		return static::$disabled;
	}
}
