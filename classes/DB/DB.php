<?php

namespace Blink;

use AllowDynamicProperties;
use Blink\Query\Expression\Table;
use Blink\DB\Database;
use stdClass;

/**
 * Static shortcuts to the default database instance.
 *
 * Every method here is the static twin of an instance method on
 * {@see Database}. `DB::table()` is the one that looks further: given a
 * model class, a `Table` or a subquery it runs on whatever instance that
 * belongs to, so `DB::table(Order::class)` reaches an archived model's own
 * database. For anything else, `DB::database("name")` names the instance.
 *
 * @extends		parent<stdClass>
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[AllowDynamicProperties]
abstract class DB extends Model {
	/**
	 * A database instance by name, the default when none is given.
	 */
	public static function database(?string $name = null): Database {
		return ($name === null)
			? Database::default()
			: Database::get($name);
	}

	/**
	 * Create a new query from specified table, on the instance the table
	 * belongs to.
	 *
	 * @param	Query|Table|string|array	$table		Table to query from
	 * @return	Query<stdClass>
	 */
	public static function table(Query|Table|string|array $table) {
		if (is_string($table) && class_exists($table) && is_subclass_of($table, Model::class))
			return $table::database() -> table($table::$table);

		// A Table or a subquery already knows which instance it lives on, so
		// `DB::table(Order::t())` keeps working for a model stored elsewhere.
		$source = is_array($table) ? ($table[0] ?? null) : $table;

		if ($source instanceof Table)
			return $source -> database -> table($table);

		if ($source instanceof Query)
			return $source -> getDatabase() -> table($table);

		return Database::default() -> table($table);
	}

	/**
	 * Run a callable inside a transaction on the default instance.
	 *
	 * @template	T
	 * @param		callable():T	$callback
	 * @return		T
	 */
	public static function transaction(callable $callback) {
		return Database::default() -> transaction($callback);
	}

	/**
	 * The engine family behind the default instance, either `mysql` or `sqlite`.
	 */
	public static function family(): string {
		return Database::default() -> family();
	}

	/**
	 * Execute raw SQL query on the default instance.
	 * Should be used only when no other method suitable.
	 *
	 * @return bool
	 */
	public static function exec(string $sql, array $params = array()) {
		return Database::default() -> execute($sql, $params);
	}

	/**
	 * Whether a table exists. A model class is looked up on its own instance.
	 *
	 * @return bool
	 */
	public static function tableExist(string $table) {
		if (class_exists($table) && is_subclass_of($table, Model::class))
			return $table::database() -> tableExist($table::$table);

		return Database::default() -> tableExist($table);
	}

	/**
	 * Save raw object to database.
	 * This method will call `$DB -> update()` directly.
	 *
	 * @param   string          $table      The database table to be checked against.
	 * @param   stdClass|array  $object     Must have an entry for "id" to map to the table specified.
	 */
	public static function update(string $table, stdClass|array $object) {
		return Database::default() -> update($table, $object);
	}

	/**
	 * Save raw object to database.
	 * This method will call `$DB -> insert()` directly.
	 *
	 * @param   string          $table      The database table to be checked against.
	 * @param   stdClass|array  $object     Must have an entry for "id" to map to the table specified.
	 * @return  int             New ID.
	 */
	public static function insert(string $table, stdClass|array $object): int {
		return Database::default() -> insert($table, $object);
	}

	/**
	 * Insert many records in as few statements as possible.
	 *
	 * Every record has to carry the same columns.
	 *
	 * @param	string					$table
	 * @param	array|\Traversable		$objects
	 * @return	int						Records inserted.
	 */
	public static function inserts(string $table, array|\Traversable $objects): int {
		if ($objects instanceof \Traversable)
			$objects = iterator_to_array($objects);

		return Database::default() -> insertMany($table, $objects);
	}

	/**
	 * Delete database records matching the conditions.
	 * This method will call `$DB -> delete()` directly.
	 *
	 * @param   string          $table      	The database table to delete records.
	 * @param   stdClass|array  $conditions     Conditions for deleting records.
	 * @return  bool
	 */
	public static function remove(string $table, stdClass|array $conditions): int {
		return Database::default() -> delete($table, $conditions);
	}
}
