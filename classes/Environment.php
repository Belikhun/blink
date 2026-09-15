<?php

namespace Blink;

use Blink\Config\Setting;

/**
 * The process environment, with an env file layered on top.
 *
 * Reading a value here is raw: {@see \Blink\env()} cleans one to a type, and
 * a config setting marked {@see \Blink\Attribute\FromEnv} pulls its own value
 * across when its section is registered.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
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

	}

	/**
	 * Every value, with anything that reads like a credential masked.
	 *
	 * The environment is shown on the error page, and a report is reachable by
	 * anyone holding its id. Settings say for themselves whether they are
	 * secret; a bare environment variable has only its name to go on.
	 *
	 * @return array<string, mixed>
	 */
	public static function masked(): array {
		$secrets = array();

		foreach (Config::settings() as $setting) {
			if ($setting -> secret && $setting -> env !== null)
				$secrets[$setting -> env] = true;
		}

		$values = array();

		foreach (static::$values as $key => $value) {
			$values[$key] = (isset($secrets[$key]) || preg_match('/(PASS|SECRET|TOKEN|CREDENTIAL|PRIVATE|SALT|_KEY|KEY_|^KEY$)/i', $key))
				? Setting::MASK
				: $value;
		}

		return $values;
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
