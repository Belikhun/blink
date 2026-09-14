<?php

namespace Blink\Trace;

use Blink\Exception\CodingError;
use Blink\Trace;

/**
 * The kinds of action an entry can record: view, create, update, delete,
 * report, and whatever a module adds from `attach()` by type-hinting this
 * registry.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ModeRegistry {
	protected array $byCode = array();

	protected array $byName = array();

	public function __construct() {
		$this -> register(Mode::VIEW, "view", "trace.mode.view", "gray", Trace::CORE);
		$this -> register(Mode::CREATE, "create", "trace.mode.create", "green", Trace::CORE);
		$this -> register(Mode::UPDATE, "update", "trace.mode.update", "blue", Trace::CORE);
		$this -> register(Mode::DELETE, "delete", "trace.mode.delete", "red", Trace::CORE);
		$this -> register(Mode::REPORT, "report", "trace.mode.report", "orange", Trace::CORE);
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
			throw new CodingError("Trace mode code <code>{$code}</code> is already defined.");

		if (isset($this -> byName[$name]))
			throw new CodingError("Trace mode <code>{$name}</code> is already defined.");

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

	public function all(): array {
		ksort($this -> byCode);
		return $this -> byCode;
	}

	public function has(int|string $mode): bool {
		if (is_int($mode))
			return isset($this -> byCode[$mode]);

		return isset($this -> byName[strtolower(trim($mode))]);
	}

	public function get(int|string $mode): array {
		if (is_int($mode)) {
			if (!isset($this -> byCode[$mode]))
				throw new CodingError("Unknown trace mode code <code>{$mode}</code>.");

			return $this -> byCode[$mode];
		}

		$mode = strtolower(trim($mode));

		if (!isset($this -> byName[$mode]))
			throw new CodingError("Unknown trace mode <code>{$mode}</code>.");

		return $this -> byName[$mode];
	}

	/**
	 * The stored code for a mode given either way, `report` when none given.
	 */
	public function normalize(int|string|null $mode = null): int {
		if ($mode === null || $mode === "")
			return Mode::REPORT;

		return $this -> get($mode)["code"];
	}
}
