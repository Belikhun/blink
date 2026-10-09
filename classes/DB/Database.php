<?php

namespace Blink\DB;

use Blink\Cache;
use Blink\DB\Exception\DatabaseConnectionFailed;
use Blink\DB\Exception\DatabaseNotRegistered;
use Blink\DB\Exception\InvalidSQLDriver;
use Blink\DB\Exception\SQLDriverNotFound;
use Blink\DB\Exception\SQLMissingParam;
use Blink\DB\Exception\SQLParamCountMismatch;
use Blink\Exception\CodingError;
use Blink\Metric\QueryMetric;
use Blink\Metric\TimingMetric;
use Blink\Model;
use Blink\Query;
use Blink\Query\Expression\Column;
use Blink\Query\Expression\Table;
use CONFIG;

/**
 * One database: a driver, the connection it holds, and the name code reaches
 * it by.
 *
 * The instance bootstrap opens from `CONFIG::$DB_*` is the default, and is
 * what every query runs on unless told otherwise. More are declared in
 * `CONFIG::$DATABASES` or registered by a module, and connect the first time
 * something asks for them:
 *
 * ```php
 * DB::table("orders") -> count();                      // the default instance
 * DB::database("archive") -> table("orders") -> count(); // a registered one
 * ```
 *
 * A model names the instance its table lives on in `Model::$database`, and a
 * Terra layer in `Layer::database()`. The name has to be registered: a model
 * or a layer never quietly lands on the default in its place.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class Database {
	const DEFAULT = "default";

	/**
	 * Settings every instance is declared with, spelled like the `DB_*`
	 * settings: the driver, then whatever that driver reads.
	 */
	const OPTIONS = array( "driver", "host", "port", "user", "pass", "name", "path", "file" );

	/**
	 * The name this instance was registered as. Bootstrap's is
	 * {@see Database::DEFAULT}.
	 *
	 * @var string
	 */
	public string $name = self::DEFAULT;

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
	 * Instances that have connected, keyed by name.
	 *
	 * @var array<string, Database>
	 */
	protected static array $instances = array();

	/**
	 * Settings of instances registered but not connected yet, keyed by name.
	 * A site may declare a database nothing in this request touches, and
	 * connecting to it anyway would make every request pay for it.
	 *
	 * @var array<string, array>
	 */
	protected static array $registered = array();

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
	 * Close the connection. The next statement is an error until
	 * {@see Database::reconnect()}; this is for an instance being dropped.
	 */
	abstract public function disconnect(): void;

	/**
	 * Where this instance points, in words safe to print: no password.
	 *
	 * @return	array{driver: string, target: string}
	 */
	abstract public function describe(): array;

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


	//* ===========================================================
	//*  Instances
	//* ===========================================================

	/**
	 * Open the default instance from config, and take note of the others.
	 *
	 * Called once during bootstrap. Calling it again is free, so a console
	 * command that needs a connection earlier than usual can ask for one.
	 */
	public static function initialize(): void {
		/**
		 * Global Database Instance. The default one, kept as a global for
		 * the code that only ever means that one.
		 *
		 * @var	\Blink\DB\Database	$DB
		 */
		global $DB;

		if (!empty($DB))
			return;

		$timing = new TimingMetric("database");

		$DB = static::open(static::DEFAULT, array(
			"driver" => CONFIG::$DB_DRIVER,
			"host" => CONFIG::$DB_HOST,
			"port" => CONFIG::$DB_PORT,
			"user" => CONFIG::$DB_USER,
			"pass" => CONFIG::$DB_PASS,
			"name" => CONFIG::$DB_NAME,
			"path" => CONFIG::$DB_PATH
		));

		static::$instances[static::DEFAULT] = $DB;

		foreach (CONFIG::$DATABASES as $name => $config)
			static::register((string) $name, (array) $config);

		$timing -> time();
	}

	/**
	 * The instance bootstrap opened from `CONFIG::$DB_*`.
	 */
	public static function default(): Database {
		if (!isset(static::$instances[static::DEFAULT]))
			static::initialize();

		return static::$instances[static::DEFAULT];
	}

	/**
	 * Declare an instance. It connects the first time {@see Database::get()}
	 * asks for it. Registering a name again replaces what was there, closing
	 * its connection if one was open.
	 *
	 * @param	string	$name		How code will reach it, ex. `archive`.
	 * @param	array	$config		Keys from {@see Database::OPTIONS}. The driver
	 * 								defaults to the one the default instance uses.
	 */
	public static function register(string $name, array $config): void {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) || $name === static::DEFAULT)
			throw new CodingError("Database::register(): <code>{$name}</code> is not a usable instance name.");

		if (isset(static::$instances[$name])) {
			static::$instances[$name] -> disconnect();
			unset(static::$instances[$name]);
		}

		static::$registered[$name] = $config;
	}

	/**
	 * Whether an instance of this name is registered, connected or not.
	 */
	public static function has(string $name): bool {
		return $name === static::DEFAULT
			|| isset(static::$instances[$name])
			|| isset(static::$registered[$name]);
	}

	/**
	 * A registered instance, connecting it on the way if this is its first
	 * use.
	 *
	 * @throws	DatabaseNotRegistered
	 */
	public static function get(string $name): Database {
		if ($name === static::DEFAULT)
			return static::default();

		if (isset(static::$instances[$name]))
			return static::$instances[$name];

		if (!isset(static::$registered[$name]))
			throw new DatabaseNotRegistered($name);

		$instance = static::open($name, static::$registered[$name]);
		static::$instances[$name] = $instance;
		unset(static::$registered[$name]);

		return $instance;
	}

	/**
	 * The instance a name means. `null` and the default's own name mean the
	 * default; anything else has to be registered.
	 *
	 * @throws	DatabaseNotRegistered
	 */
	public static function resolve(?string $name): Database {
		if ($name === null || $name === static::DEFAULT)
			return static::default();

		return static::get($name);
	}

	/**
	 * Every instance name, the default first. Nothing is connected to answer
	 * this.
	 *
	 * @return	string[]
	 */
	public static function names(): array {
		static::default();

		$names = array_merge(array_keys(static::$instances), array_keys(static::$registered));
		$names = array_values(array_unique(array_diff($names, array( static::DEFAULT ))));
		sort($names);

		return array_merge(array( static::DEFAULT ), $names);
	}

	/**
	 * Every instance, connected, keyed by name. The default comes first.
	 *
	 * @return	array<string, Database>
	 */
	public static function all(): array {
		$all = array();

		foreach (static::names() as $name)
			$all[$name] = static::get($name);

		return $all;
	}

	/**
	 * Close and drop a registered instance. The default cannot be dropped.
	 */
	public static function forget(string $name): void {
		if ($name === static::DEFAULT)
			throw new CodingError("Database::forget(): the default instance cannot be dropped.");

		if (isset(static::$instances[$name])) {
			static::$instances[$name] -> disconnect();
			unset(static::$instances[$name]);
		}

		unset(static::$registered[$name]);
		Table::forget(null, $name);
		Column::forget(null, $name);
	}

	/**
	 * Connect a driver with the given settings.
	 *
	 * @throws	DatabaseConnectionFailed
	 */
	protected static function open(string $name, array $config): Database {
		$driver = (string) ($config["driver"] ?? CONFIG::$DB_DRIVER);
		$path = CORE_ROOT . "/classes/DB/Driver/{$driver}.php";

		if (!file_exists($path))
			throw new SQLDriverNotFound($driver);

		require_once $path;
		$className = "Blink\\DB\\Driver\\{$driver}";

		if (!class_exists($className) || !in_array(Database::class, class_parents($className)))
			throw new InvalidSQLDriver($driver);

		/** @var Database $instance */
		$instance = new $className();
		$instance -> name = $name;

		$options = ($driver === "SQLite3")
			? array(
				"path" => (string) ($config["path"] ?? CONFIG::$DB_PATH),
				"file" => (string) ($config["file"] ?? "database.db")
			)
			: array(
				"host" => (string) ($config["host"] ?? "127.0.0.1"),
				"port" => (int) ($config["port"] ?? 0),
				"username" => (string) ($config["user"] ?? ""),
				"password" => (string) ($config["pass"] ?? ""),
				"database" => (string) ($config["name"] ?? "")
			);

		try {
			$instance -> connect($options);
		} catch (\Throwable $e) {
			throw new DatabaseConnectionFailed($name, $e);
		}

		return $instance;
	}

	public function isDefault(): bool {
		return $this -> name === static::DEFAULT;
	}


	//* ===========================================================
	//*  Querying this instance
	//* ===========================================================

	/**
	 * Create a new query on this instance.
	 *
	 * @param	Query|Table|string|array	$table		Table to query from
	 * @return	Query<\stdClass>
	 */
	public function table(Query|Table|string|array $table): Query {
		if (is_string($table) && class_exists($table) && is_subclass_of($table, Model::class))
			$table = $table::$table;

		return new Query("stdClass", $table, $this);
	}

	/**
	 * Create a raw SQL query on this instance. Read it with `all()` or
	 * `first()`, or run it with `exec()`.
	 *
	 * @return	Query<\stdClass>
	 */
	public function sql(string $sql, array $params = array()): Query {
		return (new Query("stdClass", "", $this)) -> sql($sql, $params);
	}

	/**
	 * Run a callable inside a transaction on this instance.
	 *
	 * Committed when the callable returns, rolled back when it throws, and the
	 * error is re-thrown either way so the caller still sees what happened.
	 *
	 * @template	T
	 * @param		callable():T	$callback
	 * @return		T
	 */
	public function transaction(callable $callback) {
		$this -> beginTransaction();

		try {
			$result = $callback();
		} catch (\Throwable $e) {
			$this -> rollback();
			throw $e;
		}

		$this -> commit();
		return $result;
	}

	/**
	 * Whether a table exists on this instance.
	 *
	 * @param	string	$table		Table name or model class name.
	 */
	public function tableExist(string $table): bool {
		if (class_exists($table) && is_subclass_of($table, Model::class))
			$table = $table::$table;

		return !empty($this -> getColumns($table));
	}

	/**
	 * Run a statement under a query metric.
	 *
	 * A statement that throws is still recorded, marked errored: it took time
	 * and belongs in the timeline, and the mark is what tells the two apart.
	 *
	 * @param	?int	$rows	What to record as the row count. Left out, the
	 * 							result stands in: its size for a list, itself
	 * 							for a number.
	 */
	protected function measure(string $sql, array $params, \Closure $statement, ?int $rows = null) {
		$metric = new QueryMetric($sql, $params, $this);

		try {
			$result = $statement();
		} catch (\Throwable $e) {
			$metric -> errored = true;
			$metric -> time(-1);
			throw $e;
		}

		$metric -> time($rows ?? (is_array($result) ? count($result) : (int) $result));
		return $result;
	}

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
		$store = Cache::instance($this -> columnCacheKey($table));

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
			Cache::remove($this -> columnCacheKey($table));
			return;
		}

		foreach ($this -> tables() as $name)
			Cache::remove($this -> columnCacheKey($name));
	}

	/**
	 * Keyed by instance as well as table: two instances can each hold a table
	 * of the same name with different columns.
	 */
	protected function columnCacheKey(string $table): string {
		return "database.columns.{$this -> name}.{$table}";
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

			$sql = "INSERT INTO `{$table}` ({$fields}) VALUES " . implode(", ", array_fill(0, count($batch), $placeholders));

			$this -> measure($sql,$params, fn () => $this -> raw($sql, $params), count($batch));
			$inserted += count($batch);
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

		$sql = "SELECT $fields FROM `$table` $select $sort";
		return $this -> measure($sql,$params, fn () => $this -> execute($sql, $params, $from, $limit));
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

		return $this -> measure($sql,$values, fn () => $this -> execute($sql, $values), 1);
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

		return $this -> measure($sql,$values, fn () => $this -> execute($sql, $values)) > 0;
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
			// `DELETE FROM` rather than `TRUNCATE`, which SQLite has no
			// statement for at all.
			$sql = "DELETE FROM `{$table}`";
			return $this -> measure($sql,array(), fn () => $this -> execute($sql));
		}

		list($select, $params) = static::whereClause($conditions);

		if (!empty($select))
			$select = "WHERE $select";

		$sql = "DELETE FROM `$table` $select";

		return $this -> measure($sql,$params, fn () => $this -> execute($sql, $params));
	}
}
