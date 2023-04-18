<?php
/**
 * Debug.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;

class Debug {
	public static String $output = "";

	public static function write(...$content) {
		static::$output .= implode(" ", $content);
	}

	public static function writeLn(...$content) {
		static::write(...$content);
		static::line();
	}

	public static function line() {
		static::$output .= "\n";
	}
}
