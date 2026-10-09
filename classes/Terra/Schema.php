<?php

namespace Blink\Terra;

use Blink\DB\Database;
use Blink\Terra\Dialect\MySQLDialect;
use Blink\Terra\Dialect\SQLiteDialect;
use Blink\Exception\CodingError;
use Blink\Query\Expression\Column as QueryColumn;
use Blink\Query\Expression\Table as QueryTable;

/**
 * What a layer calls to change the schema.
 *
 * Statements go out one at a time because both drivers reject more than one
 * per call, and each one runs through `raw()` so the query builder's checks
 * stay out of the way of DDL.
 *
 * Every call works on the current instance: the default, or whichever one
 * {@see Schema::on()} has set for the duration of a layer. A layer never
 * names it, since the stack already knows where the layer belongs.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Schema {
	/**
	 * Dialects built so far, keyed by engine family.
	 *
	 * @var array<string, Dialect>
	 */
	protected static array $dialects = array();

	/**
	 * The instance the calls below work on while a layer runs, or null for
	 * the default.
	 */
	protected static ?Database $current = null;

	/**
	 * The instance schema calls currently work on.
	 */
	public static function database(): Database {
		return static::$current ?? Database::default();
	}

	/**
	 * Run schema work on one instance.
	 *
	 * Set for the duration of the callable and put back afterwards, nested
	 * or not, so a layer that fails leaves the next one on the right
	 * database.
	 *
	 * @template	T
	 * @param		Database|string|null	$database	An instance, its name, or null for the default.
	 * @param		callable():T			$work
	 * @return		T
	 */
	public static function on(Database|string|null $database, callable $work) {
		$previous = static::$current;
		static::$current = ($database instanceof Database)
			? $database
			: Database::resolve($database);

		try {
			return $work();
		} finally {
			static::$current = $previous;
		}
	}

	public static function dialect(?Database $database = null): Dialect {
		$family = ($database ?? static::database()) -> family();

		if (isset(static::$dialects[$family]))
			return static::$dialects[$family];

		static::$dialects[$family] = match ($family) {
			"mysql" => new MySQLDialect(),
			"sqlite" => new SQLiteDialect(),
			default => throw new CodingError("Terra has no dialect for the <code>{$family}</code> database family.")
		};

		return static::$dialects[$family];
	}

	/**
	 * Forget the cached dialects. Only needed when a connection is swapped
	 * for one speaking another engine, which is a thing tests do.
	 */
	public static function forgetDialect(): void {
		static::$dialects = array();
	}

	/**
	 * @param	callable(Table): void	$builder
	 */
	public static function create(string $table, callable $builder): void {
		$blueprint = new Table($table, Table::MODE_CREATE);
		$builder($blueprint);

		static::execute(static::dialect() -> compileCreate($blueprint));
		static::forget($table);
	}

	/**
	 * @param	callable(Table): void	$builder
	 */
	public static function table(string $table, callable $builder): void {
		$blueprint = new Table($table, Table::MODE_ALTER);
		$builder($blueprint);

		static::execute(static::dialect() -> compileAlter($blueprint));
		static::forget($table);
	}

	public static function drop(string $table): void {
		static::execute(array( static::dialect() -> compileDrop($table, false) ));
		static::forget($table);
	}

	public static function dropIfExists(string $table): void {
		static::execute(array( static::dialect() -> compileDrop($table, true) ));
		static::forget($table);
	}

	/**
	 * Drop every table on the current instance.
	 */
	public static function dropAll(): void {
		$database = static::database();
		$tables = $database -> tables();

		static::execute(static::dialect() -> compileDropAll($tables));
		$database -> forgetColumns();
		QueryTable::forget(null, $database);
		QueryColumn::forget(null, $database);
	}

	public static function rename(string $from, string $to): void {
		static::execute(array( static::dialect() -> compileRename($from, $to) ));
		static::forget($from);
		static::forget($to);
	}

	public static function hasTable(string $table): bool {
		return in_array($table, static::database() -> tables(), true);
	}

	public static function hasColumn(string $table, string $column): bool {
		return array_key_exists($column, static::database() -> getColumns($table, false));
	}

	public static function hasIndex(string $table, string $index): bool {
		return static::dialect() -> hasIndex($table, $index, static::database());
	}

	/**
	 * Copy every row of a table from one instance to another, in chunks.
	 *
	 * What a layer moving a table to its own database does after creating
	 * it there: the rows go across through PHP, since no statement can read
	 * one instance and write another. Ids are kept, so the target table
	 * should be empty or free of them. The table has to exist on both sides
	 * with columns the rows fit.
	 *
	 * ```php
	 * public function database(): ?string { return "archive"; }
	 *
	 * public function raise(): void {
	 * 	Schema::create("logs", ...);                       // on the archive
	 * 	Schema::copy("logs", from: null, to: "archive");   // from the default
	 * }
	 * ```
	 *
	 * @param	Database|string|null		$from		Source instance; null for the default.
	 * @param	Database|string|null		$to			Target instance; null for the default.
	 * @param	int							$chunk		Rows per read and per insert.
	 * @param	?callable(array): ?array	$transform	Reshape a row on the way, or drop it by returning null.
	 * @param	string						$order		Column the source is read in order of.
	 * @return	int		Rows written.
	 */
	public static function copy(string $table, Database|string|null $from, Database|string|null $to, int $chunk = 500, ?callable $transform = null, string $order = "id"): int {
		$source = ($from instanceof Database) ? $from : Database::resolve($from);
		$target = ($to instanceof Database) ? $to : Database::resolve($to);

		if ($source === $target)
			throw new CodingError("Schema::copy(): <code>{$table}</code> would be copied onto itself on instance <code>{$source -> name}</code>.");

		$offset = 0;
		$copied = 0;

		while (true) {
			$rows = $source -> records($table, array(), $order, "*", $offset, max($chunk, 1));

			if (empty($rows))
				break;

			$offset += count($rows);
			$batch = array();

			foreach ($rows as $row) {
				$row = (array) $row;

				if ($transform !== null) {
					$row = $transform($row);

					if ($row === null)
						continue;
				}

				$batch[] = $row;
			}

			if (!empty($batch))
				$copied += $target -> insertMany($table, $batch, max($chunk, 1));
		}

		return $copied;
	}

	/**
	 * Run a statement Terra has no builder for. The escape hatch a layer
	 * reaches for when the schema change is engine-specific anyway.
	 */
	public static function run(string $sql, array $params = array()): void {
		static::database() -> raw($sql, $params);
	}

	/**
	 * @param	string[]	$statements
	 */
	protected static function execute(array $statements): void {
		$database = static::database();

		foreach ($statements as $sql)
			$database -> raw($sql);
	}

	/**
	 * The column cache describes a shape that no longer exists once a layer
	 * has changed it.
	 */
	protected static function forget(string $table): void {
		$database = static::database();
		$database -> forgetColumns($table);

		// The query engine keeps its own Table and Column instances, built
		// from the column list this layer has just changed.
		QueryTable::forget(array( $table ), $database);
		QueryColumn::forget(array( $table ), $database);
	}
}
