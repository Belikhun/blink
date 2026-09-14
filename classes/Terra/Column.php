<?php

namespace Blink\Terra;

/**
 * One column in a {@see Table} definition.
 *
 * The type is blink's own name for it, not the engine's. Each
 * {@see Dialect} decides what that becomes.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Column {
	const NO_DEFAULT = "[terra:no_default]";

	public string $name;

	public string $type;

	/**
	 * Type arguments, such as a string's length or a decimal's precision.
	 *
	 * @var array
	 */
	public array $options;

	public bool $nullable = false;

	public bool $unsigned = false;

	public bool $autoIncrement = false;

	public bool $primary = false;

	/**
	 * @var mixed	{@see Column::NO_DEFAULT} when none was set, so a deliberate
	 * 				null default stays distinguishable from no default at all.
	 */
	public mixed $default = self::NO_DEFAULT;

	public ?string $after = null;

	public ?string $comment = null;

	/**
	 * The table this column belongs to, so `unique()` and `index()` can
	 * register themselves where the indexes are collected.
	 */
	protected Table $table;

	public function __construct(Table $table, string $name, string $type, array $options = array()) {
		$this -> table = $table;
		$this -> name = $name;
		$this -> type = $type;
		$this -> options = $options;
	}

	public function nullable(bool $nullable = true): static {
		$this -> nullable = $nullable;
		return $this;
	}

	public function default(mixed $value): static {
		$this -> default = $value;
		return $this;
	}

	public function hasDefault(): bool {
		return $this -> default !== static::NO_DEFAULT;
	}

	public function unsigned(bool $unsigned = true): static {
		$this -> unsigned = $unsigned;
		return $this;
	}

	public function primary(bool $primary = true): static {
		$this -> primary = $primary;
		return $this;
	}

	public function autoIncrement(bool $autoIncrement = true): static {
		$this -> autoIncrement = $autoIncrement;
		return $this;
	}

	/**
	 * Place the column after another one. MySQL honours it; SQLite always
	 * appends, and ignores it.
	 */
	public function after(string $column): static {
		$this -> after = $column;
		return $this;
	}

	public function comment(string $comment): static {
		$this -> comment = $comment;
		return $this;
	}

	public function unique(string $name = null): static {
		$this -> table -> unique($this -> name, $name);
		return $this;
	}

	public function index(string $name = null): static {
		$this -> table -> index($this -> name, $name);
		return $this;
	}
}
