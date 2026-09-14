<?php

namespace Blink\Trace;

use Blink\Exception\CodingError;
use Blink\Trace;

/**
 * The severities an entry can carry.
 *
 * Stored as a code so the table stays narrow and levels sort by weight. A
 * module adds its own from `attach()` by type-hinting this registry.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LevelRegistry {
	protected array $byCode = array();

	protected array $byName = array();

	public function __construct() {
		$this -> register(10, "debug", "trace.level.debug", "gray", Trace::CORE);
		$this -> register(20, "info", "trace.level.info", "blue", Trace::CORE);
		$this -> register(30, "notice", "trace.level.notice", "cyan", Trace::CORE);
		$this -> register(40, "warning", "trace.level.warning", "yellow", Trace::CORE);
		$this -> register(50, "error", "trace.level.error", "orange", Trace::CORE);
		$this -> register(60, "critical", "trace.level.critical", "red", Trace::CORE);
		$this -> register(70, "alert", "trace.level.alert", "pink", Trace::CORE);
		$this -> register(80, "emergency", "trace.level.emergency", "purple", Trace::CORE);
	}

	/**
	 * @param	string	$label	A lang key, resolved through `__()` for display.
	 */
	public function register(
		int $code,
		string $name,
		string $label,
		string $color,
		?string $component = null
	): static {
		$name = strtolower(trim($name));

		if (isset($this -> byCode[$code]))
			throw new CodingError("Trace level code <code>{$code}</code> is already defined.");

		if (isset($this -> byName[$name]))
			throw new CodingError("Trace level <code>{$name}</code> is already defined.");

		$item = array(
			"code" => $code,
			"name" => $name,
			"label" => $label,
			"color" => $color,
			"component" => $component ?: Trace::APP
		);

		$this -> byCode[$code] = $item;
		$this -> byName[$name] = $item;
		return $this;
	}

	/**
	 * Every level, lightest first.
	 */
	public function all(): array {
		ksort($this -> byCode);
		return $this -> byCode;
	}

	public function has(int|string $level): bool {
		if (is_int($level))
			return isset($this -> byCode[$level]);

		return isset($this -> byName[strtolower(trim($level))]);
	}

	public function get(int|string $level): array {
		if (is_int($level)) {
			if (!isset($this -> byCode[$level]))
				throw new CodingError("Unknown trace level code <code>{$level}</code>.");

			return $this -> byCode[$level];
		}

		$level = strtolower(trim($level));

		if (!isset($this -> byName[$level]))
			throw new CodingError("Unknown trace level <code>{$level}</code>.");

		return $this -> byName[$level];
	}

	/**
	 * The stored code for a level given either way, `info` when none given.
	 */
	public function normalize(int|string|null $level = null): int {
		if ($level === null || $level === "")
			return $this -> get("info")["code"];

		return $this -> get($level)["code"];
	}
}
