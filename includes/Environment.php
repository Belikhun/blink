<?php

namespace Blink;

/**
 * Interface for loading, processing system environment
 * and environment file (.env).
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Environment {
	public static array $values = array();

	public static function load(string $environment = "default") {

		// Load env values from process first.
		static::$values = $_ENV;
		
		$path = ($environment === "default")
			? BASE_PATH . "/.env"
			: BASE_PATH . "/{$environment}.env";

		if (file_exists($path)) {
			$content = fileGet($path);

			$re = '/^([a-zA-Z0-9_-]+)\s*\=\s*(.*)$/m';
			$matches = null;

			if (preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0)) {
				foreach ($matches as $match) {
					list($key, $value) = static::clean($match[1], $match[2]);
					static::$values[$key] = $value;
				}
			}
		}

		// Override global config with new env values.
		$configRef = new \ReflectionClass(\CONFIG::class);
		$configProps = $configRef -> getProperties(\ReflectionProperty::IS_STATIC);
		foreach ($configProps as $prop) {
			$name = $prop -> getName();

			if (!isset(static::$values[$name]))
				continue;

			\CONFIG::$$name = static::$values[$name];
		}
	}

	public static function get(string $key, $default = null) {
		if (!isset(static::$values[$key]))
			return $default;

		return static::$values[$key];
	}

	protected static function clean(string $key, string $value) {
		$key = trim($key);
		$clval = strtolower(trim($value));
		
		if ($clval === "null")
			$value = null;
		else if (is_numeric($value))
			$value = floatval($value);
		else if (in_array($clval, ["true", "false", "on", "off", "yes", "no"]))
			$value = cleanParam($clval, TYPE_BOOL);
		else {
			if ($value[0] === "'" || $value[0] === "\"") {
				$value = trim($value, "'\"\"\n\x00\x0d\x0a");
				$value = str_replace([ "\"", "'" ], "", $value);
			} else
				$value = trim($value);
		}

		return [ $key, $value ];
	}
}
