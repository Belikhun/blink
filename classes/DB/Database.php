<?php

namespace Blink\DB;

use Blink\Cache;
use Blink\DB;
use Blink\DB\Exception\InvalidSQLDriver;
use Blink\DB\Exception\SQLDriverNotFound;
use Blink\DB\Exception\SQLMissingParam;
use Blink\DB\Exception\SQLParamCountMismatch;
use Blink\Exception\CodingError;
use Blink\Metric\QueryMetric;
use Blink\Metric\TimingMetric;
use CONFIG;

/**
 * Abstract class for Database drivers.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class Database {
	/**
	 * DB connection state
	 * @var bool
	 */
	public $connected = false;

	/**
	 * How deep the current transaction nest is. Only the outermost level
	 * actually opens and closes a transaction.
	 *
	 * @var int
	 */
	protected int $transactionDepth = 0;

	/**
	 * Return variable type from `gettype()` to
	 * current sql driver.
	 */
	abstract public function getType(string $type): string|int;

	/**
	 * The engine family this driver speaks, as Terra names its dialects.
	 * Either `mysql` or `sqlite`.
	 */
	abstract public function family(): string;

	/**
	 * Execute a statement exactly as written.
	 *
	 * Unlike {@see Database::execute()} this reads nothing into the SQL: no
	 * mode detection, no `LIMIT` injection, no rejected keywords. Schema
	 * statements and engine pragmas need that, and so does anything the query
	 * builder cannot express.
	 *
	 * @param	string				$sql
	 * @param	array				$params
	 * @return	object[]|int		Rows for a statement that returns a result
	 * 								set, affected row count otherwise.
	 */
	abstract public function raw(string $sql, array $params = array()): array|int;

	/**
	 * Drop the connection and open a new one.
	 *
	 * A long-running process outlives the server's idle timeout, and the only
	 * way back from that is a fresh connection.
	 */
	abstract public function reconnect(): void;

	/**
	 * Whether the error came from a unique index rejecting a row. This is what
	 * makes an optimistic insert safe to treat as "someone else got there
	 * first" rather than as a failure.
	 */
	abstract public function isUniqueViolation(\Throwable $e): bool;

	/**
	 * Whether the error means the connection is gone rather than the statement
	 * being wrong.
	 */
	abstract public function isConnectionLost(\Throwable $e): bool;

	/**
	 * Every table name in the current database.
	 *
	 * @return	string[]
	 */
	abstract public function tables(): array;

	abstract protected function startTransaction(): void;

	abstract protected function commitTransaction(): void;

	abstract protected function rollbackTransaction(): void;

	/**
	 * Create a new connection to database.
	 * This function might need some additional arguments
	 * based on type of drivers used.
	 *
	 * @param	array	$options	Arguments to pass into connect
	 * 								function. This will vary based on
	 * 								each sql drivers.
	 */
	abstract public function connect(array $options);

	/**
	 * Execute a SQL query.
	 *
	 * @param	string				$sql		The query
	 * @param	array				$params
	 * @param	int					$from
	 * @param	int					$limit
	 * @return	object|array|int	array of rows object in select mode, inserted record
	 * 								id in insert mode, and number of affected row
	 * 								in update mode.
	 */
	abstract public function execute(
		string $sql,
		array $params = null,
		int $from = 0,
		int $limit = 0
	): object|array|int;

	/**
	 * Fetch detailed column details from the given table name.
	 *
	 * @param	string					$table
	 * @return	\Blink\DB\ColumnInfo[]	Column array, mapped by column name.
	 */
	abstract public function fetchColumns(string $table): array;

	/**
	 * Normalize SQL parameters.
	 *
	 * @return	array
	 */
	protected function normalizeParams(string $sql, array $params) {
		// Detect objects and fix boolean value.
		foreach ($params as $key => $value) {
			if (is_object($value))
				throw new CodingError("Objects are not allowed in SQL query parameters!", "Object in key <code>{$key}</code> is illegal");

			if (is_bool($value))
				$params[$key] = $value ? 1 : 0;
		}

		$namedCount = preg_match_all('/\:([a-z][a-z0-9_]+)/', $sql, $namedMatches, PREG_SET_ORDER, 0);
		$qCount = substr_count($sql, "?");

		if ($namedCount && $qCount)
			throw new CodingError("Mixed param type detected. Do not mix named params with question mark params in SQL query");

		if (!$namedCount) {
			if ($qCount != count($params))
				throw new SQLParamCountMismatch($qCount, count($params), $sql, $params);

			return [$sql, array_values($params)];
		}

		$normalizedSql = preg_replace('/\:[a-z][a-z0-9_]+/', "?", $sql);
		$normalizedParams = array();

		foreach ($namedMatches as $match) {
			$name = $match[1];

			if (!isset($params[$name]))
				throw new SQLMissingParam($name, $sql, $params);

			$normalizedParams[] = $params[$name];
		}

		return [$normalizedSql, $normalizedParams];
	}

	/**
	 * Return detailed columns information from the given table name. This information is cached.
	 *
	 * @param	string					$table
	 * @param	bool					$cache	Read from the cache. Pass false right after a
	 * 											schema change, which the cache cannot notice.
	 * @return	\Blink\DB\ColumnInfo[]	Column array, mapped by column name.
	 */
	public function getColumns(string $table, bool $cache = true) {
		$store = Cache::instance("database.columns.{$table}");

		if ($cache && $store -> fetch() && $store -> validate())
			return $store -> content();

		$data = $this -> fetchColumns($table);
		$store -> setContent($data) -> save();
		return $data;
	}

	/**
	 * Discard the cached column list for a table, or for every table.
	 *
	 * Terra calls this after raising a layer: the columns on disk have moved
	 * and nothing else tells the cache so.
	 */
	public function forgetColumns(string $table = null): void {
		if (!empty($table)) {
			Cache::remove("database.columns.{$table}");
			return;
		}

		foreach ($this -> tables() as $name)
			Cache::remove("database.columns.{$name}");
	}

	/**
	 * Open a transaction, or join the one already open.
	 *
	 * Nesting is counted rather than nested for real: only the outermost pair
	 * of calls reaches the engine, since neither driver blink ships supports
	 * savepoints uniformly.
	 */
	public function beginTransaction(): void {
		if ($this -> transactionDepth === 0)
			$this -> startTransaction();

		$this -> transactionDepth++;
	}

	public function commit(): void {
		if ($this -> transactionDepth === 0)
			throw new CodingError("\$DB -> commit(): no transaction is open.");

		$this -> transactionDepth--;

		if ($this -> transactionDepth === 0)
			$this -> commitTransaction();
	}

	/**
	 * Roll back the whole transaction, however deep the nest is. A failure at
	 * any level voids every level above it, so unwinding one at a time would
	 * only let the outer levels commit work that is already void.
	 */
	public function rollback(): void {
		if ($this -> transactionDepth === 0)
			return;

		$this -> transactionDepth = 0;
		$this -> rollbackTransaction();
	}

	public function inTransaction(): bool {
		return $this -> transactionDepth > 0;
	}

	/**
	 * Insert many records in as few statements as possible.
	 *
	 * Every record has to carry the same columns: the statement is built once
	 * per chunk from the first record's keys.
	 *
	 * @param	string		$table
	 * @param	array		$records	Arrays or objects, all with the same keys.
	 * @param	int			$chunk		Records per statement.
	 * @return	int			Records inserted.
	 */
	public function insertMany(string $table, array $records, int $chunk = 100): int {
		if (empty($records))
			return 0;

		$records = array_values($records);
		$columns = array_keys((array) $records[0]);

		if (empty($columns))
			throw new CodingError("\$DB -> insertMany(): the first record has no columns.");

		$fields = implode(", ", array_map(fn ($name) => "`{$name}`", $columns));
		$placeholders = "(" . implode(", ", array_fill(0, count($columns), "?")) . ")";
		$inserted = 0;

		foreach (array_chunk($records, max($chunk, 1)) as $batch) {
			$params = array();

			foreach ($batch as $record) {
				$record = (array) $record;

				foreach ($columns as $name) {
					if (!array_key_exists($name, $record))
						throw new CodingError("\$DB -> insertMany(): record is missing the <code>{$name}</code> column.");

					$params[] = $record[$name];
				}
			}

			$metric = new QueryMetric(SQL_INSERT, $table);
			$sql = "INSERT INTO `{$table}` ({$fields}) VALUES " . implode(", ", array_fill(0, count($batch), $placeholders));

			$this -> raw($sql, $params);
			$inserted += count($batch);
			$metric -> time(count($batch));
		}

		return $inserted;
	}

	/**
	 * Returns the SQL WHERE conditions.
	 *
	 * @param	array	$conditions		The conditions to build the where clause.
	 * @return	array	An array list containing sql 'where' part and 'params'.
	 */
	public static function whereClause(array $conditions) {
		$conditions = is_null($conditions)
			? array()
			: $conditions;

		if (empty($conditions))
			return array("", []);

		$where = array();
		$params = array();

		foreach ($conditions as $key => $value) {
			$key = trim($key);

			if (is_null($value) || $value == "null") {
				$where[] = "$key IS NULL";
				continue;
			}

			// Process for matching multiple value
			if (is_array($value)) {
				// Don't accept empty array.
				if (empty($value))
					throw new CodingError("\$DB -> whereClause(): value of array \"$key\" is empty!");

				$cond = array();

				foreach ($value as $v) {
					if (is_numeric($v))
						$v = (float) $v;

					$cond[] = "$key = ?";
					$params[] = $v;
				}

				$where[] = "(" . implode(" OR ", $cond) . ")";
				continue;
			}

			if (is_numeric($value))
				$value = (float) $value;
			else if (is_int($value) || is_bool($value))
				$value = (int) $value;

			// Check key contain comparing operator.
			// Will need to find a better way to implement this, this
			// may open a door to an exploit if user have control of
			// $key field!
			if (str_ends_with($key, "<") || str_ends_with($key, ">") || str_ends_with($key, "=")) {
				$where[] = "$key ?";
			} else if (str_contains($value, "%")) {
				$where[] = "$key LIKE ?";
			} else {
				$where[] = "$key = ?";
			}

			$params[] = $value;
		}

		return array(implode(" AND ", $where), $params);
	}

	public static function cleanSQL(string $query) {
		// Remove SQL comments from the string
		$clean = preg_replace("/\/\*.*?\*\//s", "", $query);
		$clean = trim($clean);

		return $clean;
	}

	/**
	 * Get a number of records as an array of objects where
	 * all the given conditions met.
	 *
	 * @param	string		$table		The table to select from.
	 * @param	array		$conditions	"field" => "value" with AND in between,
	 * 									default is equal comparision. You can use
	 * 									different comparision by adding logic after
	 * 									field name (ex "abc >=" => 123).
	 * @param	string		$sort		A valid ORDER BY value.
	 * @param	string		$fields		A valid SELECT value.
	 * @return	object[]
	 */
	public function records(
		string $table,
		array $conditions = array(),
		string $sort = "",
		string $fields = "*",
		int $from = 0,
		int $limit = 0
	) {
		if (is_array($fields))
			$fields = implode(", ", $fields);

		list($select, $params) = static::whereClause($conditions);

		if (!empty($select))
			$select = "WHERE $select";

		if (!empty($sort))
			$sort = "ORDER BY $sort";

		// Record Metric
		$metric = new QueryMetric("SELECT", $table);

		$sql = "SELECT $fields FROM `$table` $select $sort";
		$results = $this -> execute($sql, $params, $from, $limit);

		$metric -> time(count($results));
		return $results;
	}

	/**
	 * Get a single database record as an object where all
	 * the given conditions met.
	 *
	 * @param	string		$table		The table to select from.
	 * @param	array		$conditions	"field" => "value" with AND in between,
	 * 									default is equal comparision. You can use
	 * 									different comparision by adding logic after
	 * 									field name (ex "abc >=" => 123).
	 * @param	string		$fields		A valid SELECT value.
	 * @param	string		$sort		A valid ORDER BY value.
	 * @return	object|null
	 */
	public function record(
		string $table,
		array $conditions = array(),
		string $fields = "*",
		string $sort = ""
	) {
		$records = $this -> records($table, $conditions, $sort, $fields, 0, 1);

		if (empty($records) || empty($records[0]))
			return null;

		return $records[0];
	}

	/**
	 * Insert a record into a table and return the "id" field if required.
	 *
	 * Some conversions and safety checks are carried out. Lobs are supported.
	 * If the return ID isn't required, then this just reports success as true/false.
	 * $data is an object containing needed data
	 *
	 * @param	string			$table		The database table to be inserted into
	 * @param	object|array	$object		A data object with values for one or more fields in the record
	 * @return	int				new id
	 */
	public function insert(string $table, array|object $object) {
		$object = (array) $object;
		$fields = array();
		$values = array();

		if (isset($object["id"]))
			unset($object["id"]);

		if (empty($object))
			throw new CodingError("\$DB -> insert(): no fields found");

		foreach ($object as $key => $value) {
			if (is_null($value) || $value == "null")
				continue;

			$fields[] = trim($key, " *\t\n\r\0\x0B");
			$values[] = $value;
		}

		$questions = array_fill(0, count($fields), "?");
		$questions = implode(", ", $questions);
		$fields = implode(", ", $fields);
		$sql = "INSERT INTO `$table` ($fields) VALUES ($questions)";

		// Record Metric
		$metric = new QueryMetric("INSERT", $table);

		$results = $this -> execute($sql, $values);
		$metric -> time(1);
		return $results;
	}

	/**
	 * Update an record from database.
	 *
	 * @param	string			$table		The database table to be inserted into
	 * @param	object|array	$object		A data object with values for one or more fields in the record
	 * @param	string			$primary	Primary key name
	 * @return	bool
	 */
	public function update(string $table, array|object $object, string $primary = "id") {
		$object = (array) $object;

		if (empty($object[$primary]))
			throw new CodingError("\$DB -> update(): id field must be specified");

		$id = $object[$primary];
		unset($object[$primary]);

		if (empty($object))
			throw new CodingError("\$DB -> update(): no fields found");

		$sets = array();
		$values = array();

		foreach ($object as $field => $value) {
			// if (is_null($value))
			// 	$value = "NULL";

			$field = trim($field, " *\t\n\r\0\x0B");
			$sets[] = "$field = ?";
			$values[] = $value;
		}

		// Last ? in query.
		$values[] = $id;

		$sets = implode(", ", $sets);
		$sql = "UPDATE {$table} SET $sets WHERE {$table}.{$primary} = ?";

		// Record Metric
		$metric = new QueryMetric(SQL_UPDATE, $table);

		$affected = $this -> execute($sql, $values);
		$metric -> time($affected);
		return ($affected > 0);
	}

	/**
	 * Test whether a record exists in a table where all
	 * the given conditions met.
	 *
	 * @param	string		$table		The table to select from.
	 * @param	array		$conditions	"field" => "value" with AND in between,
	 * 									default is equal comparision. You can use
	 * 									different comparision by adding logic after
	 * 									field name (ex "abc >=" => 123).
	 *
	 * @return	bool
	 */
	public function exist(string $table, array $conditions = array()) {
		// Select 'X' to find if a row exist!
		// https://stackoverflow.com/questions/7624376/what-is-select-x
		$record = $this -> record($table, $conditions, "'x'");
		return !empty($record);
	}

	/**
	 * Count the records in a table which match a particular WHERE clause.
	 *
	 * @param	string		$table		The table to select from.
	 * @param	array		$conditions	"field" => "value" with AND in between,
	 * 									default is equal comparision. You can use
	 * 									different comparision by adding logic after
	 * 									field name (ex "abc >=" => 123).
	 *
	 * @return	int
	 */
	public function count(string $table, array $conditions = array()) {
		$count = $this -> record($table, $conditions, "COUNT('x')");
		$count = (int) $count -> {"COUNT('x')"};

		if ($count < 0)
			throw new CodingError("\$DB -> count() expects the first field to contain non-negative number from COUNT(), \"$count\" found instead.");

		return (int) $count;
	}

	/**
	 * Delete the records from a table where all the given conditions met.
	 * If conditions not specified, table is truncated.
	 *
	 * @param	string		$table		The table to select from.
	 * @param	array		$conditions	"field" => "value" with AND in between,
	 * 									default is equal comparision. You can use
	 * 									different comparision by adding logic after
	 * 									field name (ex "abc >=" => 123).
	 *
	 * @return	int			Affected rows
	 */
	public function delete(string $table, array $conditions = array()) {
		if (empty($conditions)) {
			// Record Metric
			$metric = new QueryMetric(SQL_TRUNCATE, $table);

			// `DELETE FROM` rather than `TRUNCATE`, which SQLite has no
			// statement for at all.
			$affected = $this -> execute("DELETE FROM `{$table}`");
			$metric -> time($affected);
			return $affected;
		}

		list($select, $params) = static::whereClause($conditions);

		if (!empty($select))
			$select = "WHERE $select";

		$sql = "DELETE FROM `$table` $select";

		// Record Metric
		$metric = new QueryMetric(SQL_DELETE, $table);

		$affected = $this -> execute($sql, $params);
		$metric -> time($affected);
		return $affected;
	}

	/**
	 * Connect the global `$DB` instance to the driver named in config.
	 *
	 * Called once during bootstrap. Calling it again is free, so a console
	 * command that needs a connection earlier than usual can ask for one.
	 */
	public static function initialize(): void {
		/**
		 * Global Database Instance. Initialized based on type of
		 * SQL driver specified in config.
		 *
		 * @var	\Blink\DB\Database	$DB
		 */
		global $DB;

		if (!empty($DB))
			return;

		$driver = CONFIG::$DB_DRIVER;
		$path = CORE_ROOT . "/classes/DB/Driver/{$driver}.php";

		if (!file_exists($path))
			throw new SQLDriverNotFound($driver);

		$timing = new TimingMetric("database");
		require_once $path;
		$className = "Blink\\DB\\Driver\\{$driver}";

		if (!class_exists($className) || !in_array(Database::class, class_parents($className)))
			throw new InvalidSQLDriver($driver);

		$DB = new $className();

		$DB -> connect(($driver === "SQLite3")
			? array( "path" => CONFIG::$DB_PATH )
			: array(
				"host" => CONFIG::$DB_HOST,
				"username" => CONFIG::$DB_USER,
				"password" => CONFIG::$DB_PASS,
				"database" => CONFIG::$DB_NAME
			));

		DB::$instance = $DB;
		$timing -> time();
	}
}
