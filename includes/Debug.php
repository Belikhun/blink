<?php

namespace Blink;

/**
 * Debug interface for storing debugging information.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Debug {
	public static string $output = "";

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
