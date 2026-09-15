<?php

namespace Blink;

use Blink\Config\Exception\UnknownSetting;
use Blink\Config\Section;
use Blink\Config\Setting;

/**
 * Everything the site is configured with, and where each value came from.
 *
 * Settings stay what they have always been: typed static properties an
 * application reads directly.
 *
 * ```php
 * CONFIG::$DB_HOST;
 * ```
 *
 * This is the registry around them. It knows every config class the site has
 * registered, what each setting is for, which environment variable fills it,
 * and which ones must never be printed. That is what lets a module ship its
 * own settings, `config:list` describe them, and the environment fill them
 * without guessing at names.
 *
 * ```php
 * Config::get("billing.endpoint");
 * Config::section("billing") -> settings();
 * ```
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
final class Config {
	/**
	 * @var array<string, Section>	Keyed by prefix.
	 */
	protected static array $sections = array();

	/**
	 * Register a config class, fill it from the environment and create the
	 * directories it names.
	 *
	 * Registering the same class twice returns what was registered the first
	 * time, so a module listing a config class its dependency also lists costs
	 * nothing.
	 *
	 * @param	class-string	$class
	 */
	public static function register(string $class): Section {
		$class = ltrim($class, "\\");

		foreach (static::$sections as $section) {
			if ($section -> class === $class)
				return $section;
		}

		$section = new Section($class);

		// A second class claiming a prefix would quietly shadow the first when
		// read by key, so the later one is kept apart under its own name.
		if (isset(static::$sections[$section -> prefix]) && static::$sections[$section -> prefix] -> class !== $class)
			$section -> prefix = $section -> prefix . "." . strtolower(str_replace("\\", ".", $class));

		static::$sections[$section -> prefix] = $section;

		$section -> applyEnvironment(Environment::$values);
		$section -> ensurePaths();

		return $section;
	}

	/**
	 * @return array<string, Section>
	 */
	public static function sections(): array {
		ksort(static::$sections);
		return static::$sections;
	}

	public static function section(string $prefix): ?Section {
		return static::$sections[strtolower($prefix)] ?? null;
	}

	/**
	 * Every setting of every section, keyed by key.
	 *
	 * @return array<string, Setting>
	 */
	public static function settings(): array {
		$settings = array();

		foreach (static::sections() as $section)
			$settings = array_merge($settings, $section -> settings());

		ksort($settings);
		return $settings;
	}

	public static function has(string $key): bool {
		return static::find($key) !== null;
	}

	protected static function find(string $key): ?Setting {
		$key = strtolower(trim($key));

		foreach (static::$sections as $section) {
			$setting = $section -> setting($key);

			if ($setting !== null)
				return $setting;
		}

		return null;
	}

	/**
	 * @throws	UnknownSetting
	 */
	public static function setting(string $key): Setting {
		$setting = static::find($key);

		if ($setting === null)
			throw new UnknownSetting($key, static::suggest($key));

		return $setting;
	}

	/**
	 * Read a setting by name.
	 *
	 * Reading the property is the shorter way and the one to prefer. This is
	 * for the cases where the name is not known until runtime: a console
	 * argument, a listing, a template.
	 */
	public static function get(string $key, mixed $default = null): mixed {
		$setting = static::find($key);

		return ($setting !== null)
			? $setting -> value()
			: $default;
	}

	/**
	 * Write a setting by name, converting the value to its declared type.
	 *
	 * @throws	UnknownSetting
	 */
	public static function set(string $key, mixed $value): void {
		$setting = static::setting($key);
		$setting -> set($setting -> coerce($value));
	}

	/**
	 * Keys close enough to what was asked for to be worth offering.
	 *
	 * @return string[]
	 */
	public static function suggest(string $key): array {
		$key = strtolower(trim($key));
		$matches = array();

		foreach (static::settings() as $candidate => $setting) {
			// The tail of a key is the part people remember, so a bare
			// `db_host` finds `blink.db_host`.
			if (str_contains($candidate, $key) || levenshtein($key, $candidate) <= 3)
				$matches[] = $candidate;
		}

		return array_slice($matches, 0, 5);
	}

	/**
	 * Every setting as `key => displayable value`, with secrets masked.
	 *
	 * @return array<string, string>
	 */
	public static function display(bool $reveal = false): array {
		$values = array();

		foreach (static::settings() as $key => $setting)
			$values[$key] = $setting -> display($reveal);

		return $values;
	}

	/**
	 * Something that will fail at runtime.
	 */
	const PROBLEM = "problem";

	/**
	 * Something worth a look that may well be deliberate.
	 */
	const NOTE = "note";

	/**
	 * Report what is wrong, and what is only worth mentioning.
	 *
	 * The two are kept apart because only one of them means the site is
	 * misconfigured. A directory blink cannot write to will fail the first
	 * time something needs it. An empty secret may simply be one this
	 * deployment has no use for, and nothing here can tell the difference.
	 *
	 * @return	array<int, array{key: string, severity: string, message: string}>
	 */
	public static function check(): array {
		$found = array();

		foreach (static::settings() as $key => $setting) {
			if ($setting -> path) {
				$directory = (string) $setting -> value();

				if ($directory === "") {
					$found[] = array( "key" => $key, "severity" => static::PROBLEM, "message" => "names no directory" );
					continue;
				}

				if (!file_exists($directory)) {
					$found[] = array( "key" => $key, "severity" => static::PROBLEM, "message" => "directory does not exist and could not be created: {$directory}" );
					continue;
				}

				if (!is_writable($directory))
					$found[] = array( "key" => $key, "severity" => static::PROBLEM, "message" => "directory is not writable: {$directory}" );

				continue;
			}

			if ($setting -> secret && $setting -> value() === "" && $setting -> env !== null)
				$found[] = array( "key" => $key, "severity" => static::NOTE, "message" => "is empty; set {$setting -> env} if this deployment needs it" );
		}

		return $found;
	}

	/**
	 * Drop every registered section. For a process that boots a site more than
	 * once.
	 */
	public static function forget(): void {
		static::$sections = array();
	}
}
