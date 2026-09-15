<?php

namespace Blink\Config;

use ReflectionClass;
use ReflectionProperty;
use Blink\Config\Exception\InvalidSection;

/**
 * One config class and the settings it declares.
 *
 * Blink's own settings are a section, and so is every config class a module
 * ships. A section owns a prefix, which is what keeps two modules from
 * colliding over the same setting name.
 *
 * ```php
 * class BillingConfig {
 * 	const PREFIX = "billing";
 *
 * 	#[FromEnv("BILLING_ENDPOINT")]
 * 	public static string $ENDPOINT = "https://billing.example.com";
 * }
 * ```
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Section {
	/**
	 * @var class-string
	 */
	public string $class;

	public string $prefix;

	/**
	 * @var array<string, Setting>	Keyed by the setting's full key.
	 */
	protected array $settings = array();

	/**
	 * @param	class-string	$class
	 * @throws	InvalidSection
	 */
	public function __construct(string $class) {
		$class = ltrim($class, "\\");

		if (!class_exists($class))
			throw new InvalidSection($class, "the class does not exist");

		$this -> class = $class;
		$this -> prefix = static::prefixOf($class);

		$reflection = new ReflectionClass($class);

		foreach ($reflection -> getProperties(ReflectionProperty::IS_STATIC) as $property) {
			// A setting has to be readable and writable from anywhere, which
			// is what makes `CONFIG::$X` work at all.
			if (!$property -> isPublic())
				continue;

			$setting = new Setting($this, $property);
			$this -> settings[$setting -> key] = $setting;
		}
	}

	/**
	 * The prefix a config class declares, or one derived from its name.
	 *
	 * `App\Config\BillingConfig` without a `PREFIX` becomes `billing`, which
	 * is right often enough to be worth not requiring.
	 */
	public static function prefixOf(string $class): string {
		if (defined("{$class}::PREFIX"))
			return strtolower(trim((string) constant("{$class}::PREFIX")));

		$parts = explode("\\", $class);
		$name = (string) end($parts);

		if (str_ends_with($name, "Config") && $name !== "Config")
			$name = substr($name, 0, -6);

		return strtolower($name);
	}

	/**
	 * @return array<string, Setting>
	 */
	public function settings(): array {
		return $this -> settings;
	}

	public function setting(string $key): ?Setting {
		return $this -> settings[$key] ?? null;
	}

	/**
	 * Fill every bound setting from the environment.
	 *
	 * @return	Setting[]	The settings the environment filled.
	 */
	public function applyEnvironment(array $values): array {
		$applied = array();

		foreach ($this -> settings as $setting) {
			if ($setting -> applyEnvironment($values))
				$applied[] = $setting;
		}

		return $applied;
	}

	/**
	 * Create the directories this section's path settings name.
	 *
	 * @return	string[]	The directories that had to be created.
	 */
	public function ensurePaths(): array {
		$created = array();

		foreach ($this -> settings as $setting) {
			if (!$setting -> path)
				continue;

			$directory = (string) $setting -> value();

			if ($directory === "" || file_exists($directory))
				continue;

			if (@mkdir($directory, 0777, true))
				$created[] = $directory;
		}

		return $created;
	}
}
