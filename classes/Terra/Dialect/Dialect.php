<?php

namespace Blink\Terra;

use Blink\DB\Database;
use Blink\Terra\Exception\TerraUnsupported;

/**
 * Turns a {@see Table} into statements one engine understands.
 *
 * Every compile returns a list of statements run one at a time, because
 * neither driver blink ships accepts more than one per call.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class Dialect {
	/**
	 * @return	string[]
	 */
	abstract public function compileCreate(Table $table): array;

	/**
	 * @return	string[]
	 */
	abstract public function compileAlter(Table $table): array;

	abstract public function compileDrop(string $table, bool $ifExists): string;

	abstract public function compileRename(string $from, string $to): string;

	/**
	 * The engine's name for a column type.
	 */
	abstract public function typeOf(Column $column): string;

	/**
	 * Whether an index exists, asked of the engine's own catalogue.
	 */
	abstract public function hasIndex(string $table, string $index, Database $database): bool;

	/**
	 * Statements that drop every table, in an order that satisfies whatever
	 * foreign keys are in place.
	 *
	 * @return	string[]
	 */
	abstract public function compileDropAll(array $tables): array;

	public function compileIndex(string $table, Index $index): string {
		$columns = implode(", ", array_map(fn ($name) => $this -> wrap($name), $index -> columns));
		$unique = $index -> unique ? "UNIQUE " : "";

		return "CREATE {$unique}INDEX {$this -> wrap($index -> name)} ON {$this -> wrap($table)} ({$columns})";
	}

	public function compileDropIndex(string $table, string $name): string {
		return "DROP INDEX {$this -> wrap($name)}";
	}

	/**
	 * The column definition shared by both engines, minus whatever each one
	 * spells differently.
	 */
	protected function compileColumn(Column $column): string {
		$sql = "{$this -> wrap($column -> name)} {$this -> typeOf($column)}";

		if (!$column -> nullable)
			$sql .= " NOT NULL";

		if ($column -> hasDefault())
			$sql .= " DEFAULT " . $this -> quote($column -> default);

		return $sql;
	}

	public function wrap(string $name): string {
		return "`" . str_replace("`", "``", $name) . "`";
	}

	public function quote(mixed $value): string {
		if ($value === null)
			return "NULL";

		if (is_bool($value))
			return $value ? "1" : "0";

		if (is_int($value) || is_float($value))
			return (string) $value;

		return "'" . str_replace("'", "''", (string) $value) . "'";
	}

	/**
	 * Refuse something the engine in front of us cannot do, naming what to do
	 * instead.
	 */
	protected function reject(string $what, string $instead): never {
		throw new TerraUnsupported($what, $instead);
	}
}
