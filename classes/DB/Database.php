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
	 * Return variable type from `gettype()` to
	 * current sql driver.
	 */
	abstract public function getType(string $type): string|int;

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

		$namedCount = preg_match_all('/(?<!:):([a-z][a-z0-9_])*/', $sql, $namedMatches);
		$qCount = substr_count($sql, "?");

		if ($namedCount && $qCount)
			throw new CodingError("Mixed param type detected. Do not mix named params with question mark params in SQL query");

		if (!$namedCount) {
			if ($qCount != count($params))
				throw new SQLParamCountMismatch($qCount, count($params), $sql, $params);

			return [$sql, array_values($params)];
		}

		$normalizedSql = preg_replace('/(?<!:):[a-z][a-z0-9_]*/', "?", $sql);
		$normalizedParams = array();

		foreach ($namedMatches as $match) {
			$name = $match[1];

			if (!isset($params[$name]))
				throw new SQLMissingParam($name, $sql, $params);

			$normalizedParams[] = $params;
		}

		return [$normalizedSql, $normalizedParams];
	}

	/**
	 * Return detailed columns information from the given table name. This information is cached.
	 * 
	 * @param	string					$table
	 * @param	bool					$cache
	 * @return	\Blink\DB\ColumnInfo[]	Column array, mapped by column name.
	 */
	public function getColumns(string $table, bool $cache = true) {
		$cache = Cache::instance("database.columns.{$table}");

		if ($cache -> validate())
			return $cache -> content();

		$data = $this -> fetchColumns($table);
		$cache -> setContent($data);
		return $data;
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
		$metric = new QueryMetric("INSERT", $table);
		
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
			$metric = new QueryMetric("TRUNCATE", $table);
			
			$affected = $this -> execute("TRUNCATE TABLE {" . $table . "}");
			$metric -> time($affected);
			return $affected;
		}
		
		list($select, $params) = static::whereClause($conditions);

		if (!empty($select))
			$select = "WHERE $select";

		$sql = "DELETE FROM `$table` $select";

		// Record Metric
		$metric = new QueryMetric("TRUNCATE", $table);

		$affected = $this -> execute($sql, $params);
		$metric -> time($affected);
			return $affected;
	}
}

/**
 * Function to initialize the global `$DB`
 * variable.
 */
function initializeDB() {
	$dbTiming = new TimingMetric("database");

	/**
	 * Global Database Instance. Initialized based on type of
	 * SQL driver specified in config.
	 * 
	 * @var	\Blink\DB\Database	$DB
	 */
	global $DB;

	// $DB is initialized, we don't need to do anything.
	if (!empty($DB))
		return;

	$DB_DRIVER_PATH = CORE_ROOT . "/db/DB." . CONFIG::$DB_DRIVER . ".php";

	if (file_exists($DB_DRIVER_PATH)) {
		require_once $DB_DRIVER_PATH;
		$className = "Blink\\DB\\Driver\\" . CONFIG::$DB_DRIVER;

		if (!class_exists($className) || !in_array("Blink\\DB\\Driver", class_parents($className)))
			throw new InvalidSQLDriver(CONFIG::$DB_DRIVER);

		$DB = new $className();

		switch (CONFIG::$DB_DRIVER) {
			case "SQLite3": {
				$DB -> connect(array(
					"path" => CONFIG::$DB_PATH
				));
				
				break;
			}

			default: {
				// We default the config arguments to standard info
				// like mysqli.
				$DB -> connect(array(
					"host" => CONFIG::$DB_HOST,
					"username" => CONFIG::$DB_USER,
					"password" => CONFIG::$DB_PASS,
					"database" => CONFIG::$DB_NAME
				));

				break;
			}
		}

		DB::$instance = $DB;
		$dbTiming -> time();
	} else
		throw new SQLDriverNotFound(CONFIG::$DB_DRIVER);
}
