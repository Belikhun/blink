<?php

namespace Blink;

use AllowDynamicProperties;
use Blink\Query\Expression\Table;
use Blink\DB\Database;
use stdClass;

/**
 * Class represent a base model with properties like stdClass.
 * This class also contain DB APIs from Model class.
 *
 * @extends		parent<stdClass>
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[AllowDynamicProperties]
abstract class DB extends Model {
	public static Database $instance;

	/**
	 * Create a new query from specified table.
	 *
	 * @param	Query|Table|string|array	$table		Table to query from
	 * @return	Query<stdClass>
	 */
	public static function table(Query|Table|string|array $table) {
		if (is_string($table) && class_exists($table) && is_subclass_of($table, Model::class))
			$table = $table::$table;

		return new Query("stdClass", $table);
	}

	/**
	 * Run a callable inside a database transaction.
	 *
	 * Committed when the callable returns, rolled back when it throws, and the
	 * error is re-thrown either way so the caller still sees what happened.
	 *
	 * @template	T
	 * @param		callable():T	$callback
	 * @return		T
	 */
	public static function transaction(callable $callback) {
		global $DB;

		$DB -> beginTransaction();

		try {
			$result = $callback();
		} catch (\Throwable $e) {
			$DB -> rollback();
			throw $e;
		}

		$DB -> commit();
		return $result;
	}

	/**
	 * The engine family behind the connection, either `mysql` or `sqlite`.
	 */
	public static function family(): string {
		global $DB;
		return $DB -> family();
	}

	/**
	 * Execute raw SQL query.
	 * Should be used only when no other method suitable.
	 *
	 * @return bool
	 */
	public static function exec(string $sql, array $params = array()) {
		global $DB;
		return $DB -> execute($sql, $params);
	}

	/**
	 * Verify if the sepcified table exist in the database.
	 *
	 * @return bool
	 */
	public static function tableExist(string $table) {
		global $DB;

		// When an model passed, update to use the model's class.
		if (class_exists($table) && is_subclass_of($table, Model::class))
			$table = $table::$table;

		return !empty($DB -> getColumns($table));
	}

	/**
	 * Save raw object to database.
	 * This method will call `$DB -> update()` directly.
	 *
	 * @param   string          $table      The database table to be checked against.
	 * @param   stdClass|array  $object     Must have an entry for "id" to map to the table specified.
	 */
	public static function update(string $table, stdClass|array $object) {
		global $DB;
		return $DB -> update($table, $object);
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
		global $DB;
		return $DB -> insert($table, $object);
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
		global $DB;

		if ($objects instanceof \Traversable)
			$objects = iterator_to_array($objects);

		return $DB -> insertMany($table, $objects);
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
		global $DB;
		return $DB -> delete($table, $conditions);
	}
}
