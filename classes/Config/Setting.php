<?php

namespace Blink\Config;

use ReflectionNamedType;
use ReflectionProperty;
use Blink\Attribute\FromEnv;
use Blink\Attribute\PathProperty;
use Blink\Attribute\Secret;
use Blink\Config\Exception\InvalidSettingValue;

/**
 * One setting: a static property on a config class, and everything blink knows
 * about it.
 *
 * The property itself stays the way an application reads it,
 * `CONFIG::$DB_HOST`, typed and completed by the editor. This describes that
 * property so the console can list it, the environment can fill it and the
 * error page can decide whether it is safe to show.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Setting {
	const MASK = "********";

	public Section $section;

	/**
	 * The property, as it is declared: `DB_HOST`.
	 */
	public string $property;

	/**
	 * How the setting is addressed by name: `blink.db_host`.
	 */
	public string $key;

	/**
	 * The declared type, or `mixed` when the property has none.
	 */
	public string $type;

	public bool $nullable = false;

	/**
	 * The value in the code, before `config.php` or the environment had a say.
	 */
	public mixed $default = null;

	/**
	 * The environment variable this setting is read from, when it has one.
	 */
	public ?string $env = null;

	public bool $secret = false;

	/**
	 * Whether this setting names a directory blink creates and writes to.
	 */
	public bool $path = false;

	/**
	 * The first paragraph of the property's docblock.
	 */
	public ?string $description = null;

	/**
	 * Where the current value came from: `default`, `config` or `env`.
	 */
	public string $source = "default";

	public function __construct(Section $section, ReflectionProperty $property) {
		$this -> section = $section;
		$this -> property = $property -> getName();
		$this -> key = $section -> prefix . "." . strtolower($this -> property);

		$type = $property -> getType();

		if ($type instanceof ReflectionNamedType) {
			$this -> type = $type -> getName();
			$this -> nullable = $type -> allowsNull();
		} else {
			// A union, or no declaration at all. Either way nothing can be
			// coerced into it safely, so the raw value is passed through.
			$this -> type = "mixed";
			$this -> nullable = true;
		}

		$this -> default = $property -> getDefaultValue();
		$this -> description = static::describe($property -> getDocComment() ?: "");

		foreach ($property -> getAttributes(FromEnv::class) as $attribute)
			$this -> env = $attribute -> newInstance() -> name;

		$this -> secret = !empty($property -> getAttributes(Secret::class));
		$this -> path = !empty($property -> getAttributes(PathProperty::class));

		// `config.php` has already run by the time a section is registered, so
		// anything differing from the code default came from there.
		if ($this -> value() !== $this -> default)
			$this -> source = "config";
	}

	public function value(): mixed {
		$class = $this -> section -> class;
		$property = $this -> property;

		return $class::${$property};
	}

	public function set(mixed $value): void {
		$class = $this -> section -> class;
		$property = $this -> property;

		$class::${$property} = $value;
	}

	/**
	 * Whether the current value differs from the one declared in code.
	 */
	public function isChanged(): bool {
		return $this -> value() !== $this -> default;
	}

	/**
	 * The value as it is safe to display: a secret becomes a mask, an array
	 * or object becomes JSON.
	 */
	public function display(bool $reveal = false): string {
		$value = $this -> value();

		if ($this -> secret && !$reveal)
			return ($value === null || $value === "") ? "" : static::MASK;

		return static::stringify($value);
	}

	public static function stringify(mixed $value): string {
		if ($value === null)
			return "null";

		if (is_bool($value))
			return $value ? "true" : "false";

		if (is_array($value) || is_object($value))
			return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		return (string) $value;
	}

	/**
	 * Fill this setting from the environment, if it is bound to a variable
	 * that is set.
	 *
	 * @return	bool	Whether the value came from the environment.
	 */
	public function applyEnvironment(array $values): bool {
		if ($this -> env === null || !array_key_exists($this -> env, $values))
			return false;

		$this -> set($this -> coerce($values[$this -> env]));
		$this -> source = "env";
		return true;
	}

	/**
	 * Convert a value to this setting's declared type.
	 *
	 * An environment variable is text even when it reads as a number, and
	 * assigning text to a typed static property is a fatal error rather than
	 * something a site can recover from, so the conversion happens here where
	 * a bad value can be reported against the setting that rejected it.
	 *
	 * @throws	InvalidSettingValue
	 */
	public function coerce(mixed $value): mixed {
		if ($value === null) {
			if ($this -> nullable)
				return null;

			throw new InvalidSettingValue($this, "null", "a " . $this -> type);
		}

		return match ($this -> type) {
			"string" => is_scalar($value) ? (string) $value : throw new InvalidSettingValue($this, Setting::stringify($value), "a string"),
			"int" => static::toInt($this, $value),
			"float" => is_numeric($value) ? (float) $value : throw new InvalidSettingValue($this, Setting::stringify($value), "a number"),
			"bool" => static::toBool($this, $value),
			"array" => static::toArray($this, $value),
			default => $value
		};
	}

	protected static function toInt(Setting $setting, mixed $value): int {
		if (!is_numeric($value))
			throw new InvalidSettingValue($setting, Setting::stringify($value), "a whole number");

		// A float that is not whole would silently lose its fraction, and a
		// port or a byte count read as "8.5" is a mistake worth naming.
		if ((float) $value !== floor((float) $value))
			throw new InvalidSettingValue($setting, Setting::stringify($value), "a whole number");

		return (int) $value;
	}

	protected static function toBool(Setting $setting, mixed $value): bool {
		if (is_bool($value))
			return $value;

		$clean = strtolower(trim((string) $value));

		if (in_array($clean, array( "1", "true", "yes", "on" ), true))
			return true;

		if (in_array($clean, array( "0", "false", "no", "off", "" ), true))
			return false;

		throw new InvalidSettingValue($setting, Setting::stringify($value), "true or false");
	}

	protected static function toArray(Setting $setting, mixed $value): array {
		if (is_array($value))
			return $value;

		$text = trim((string) $value);

		if ($text === "")
			return array();

		// JSON first, so a list of anything but plain strings can still be
		// written out in full.
		if (str_starts_with($text, "[") || str_starts_with($text, "{")) {
			$decoded = json_decode($text, true);

			if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded))
				throw new InvalidSettingValue($setting, $text, "valid JSON");

			return $decoded;
		}

		return array_values(array_filter(array_map("trim", explode(",", $text)), fn ($item) => $item !== ""));
	}

	/**
	 * The first paragraph of a docblock, as one line.
	 */
	protected static function describe(string $doc): ?string {
		if ($doc === "")
			return null;

		$lines = array();

		foreach (explode("\n", $doc) as $line) {
			$line = trim($line);
			$line = preg_replace('/^\/\*\*|^\*\/|^\*\s?|\*\/$/', "", $line);
			$line = trim((string) $line);

			// The tags below the prose describe the type, which the property
			// declaration already says.
			if (str_starts_with($line, "@"))
				break;

			// A blank line ends the summary paragraph.
			if ($line === "") {
				if (!empty($lines))
					break;

				continue;
			}

			$lines[] = $line;
		}

		$description = trim(implode(" ", $lines));
		return ($description === "") ? null : $description;
	}
}
