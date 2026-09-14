<?php

namespace Blink\Terra;

use Blink\Terra\Dialect\MySQLDialect;
use Blink\Terra\Dialect\SQLiteDialect;
use Blink\Exception\CodingError;

/**
 * What a layer calls to change the schema.
 *
 * Statements go out one at a time because both drivers reject more than one
 * per call, and each one runs through `raw()` so the query builder's checks
 * stay out of the way of DDL.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Schema {
	protected static ?Dialect $dialect = null;

	public static function dialect(): Dialect {
		if (static::$dialect !== null)
			return static::$dialect;

		global $DB;
		$family = $DB -> family();

		static::$dialect = match ($family) {
			"mysql" => new MySQLDialect(),
			"sqlite" => new SQLiteDialect(),
			default => throw new CodingError("Terra has no dialect for the <code>{$family}</code> database family.")
		};

		return static::$dialect;
	}

	/**
	 * Forget the cached dialect. Only needed when the connection is swapped
	 * for one speaking another engine, which is a thing tests do.
	 */
	public static function forgetDialect(): void {
		static::$dialect = null;
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
	 * Drop every table in the database.
	 */
	public static function dropAll(): void {
		global $DB;

		$tables = $DB -> tables();
		static::execute(static::dialect() -> compileDropAll($tables));
		$DB -> forgetColumns();
	}

	public static function rename(string $from, string $to): void {
		static::execute(array( static::dialect() -> compileRename($from, $to) ));
		static::forget($from);
		static::forget($to);
	}

	public static function hasTable(string $table): bool {
		global $DB;
		return in_array($table, $DB -> tables(), true);
	}

	public static function hasColumn(string $table, string $column): bool {
		global $DB;
		return array_key_exists($column, $DB -> getColumns($table, false));
	}

	public static function hasIndex(string $table, string $index): bool {
		return static::dialect() -> hasIndex($table, $index);
	}

	/**
	 * Run a statement Terra has no builder for. The escape hatch a layer
	 * reaches for when the schema change is engine-specific anyway.
	 */
	public static function run(string $sql, array $params = array()): void {
		global $DB;
		$DB -> raw($sql, $params);
	}

	/**
	 * @param	string[]	$statements
	 */
	protected static function execute(array $statements): void {
		global $DB;

		foreach ($statements as $sql)
			$DB -> raw($sql);
	}

	/**
	 * The column cache describes a shape that no longer exists once a layer
	 * has changed it.
	 */
	protected static function forget(string $table): void {
		global $DB;
		$DB -> forgetColumns($table);
	}
}
