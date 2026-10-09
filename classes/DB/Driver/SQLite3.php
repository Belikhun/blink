<?php

namespace Blink\DB\Driver;

use Blink\DB\ColumnInfo;
use Blink\DB\Database;
use Blink\DB\Enum\ColumnType;
use Blink\DB\Exception\QueryException;
use Blink\DB\Exception\TableNotFound;
use Blink\Exception\BaseException;
use Blink\Exception\CodingError;

/**
 * SQLite3 database driver.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class SQLite3 extends Database {

	public \SQLite3 $instance;

	/**
	 * Database data base path
	 * @var	string
	 */
	public $path;
	
	/**
	 * Database file name
	 * @var	string
	 */
	public $file = "database.db";

	/**
	 * DB connection state
	 * @var bool
	 */
	public $connected = false;

	public function family(): string {
		return "sqlite";
	}

	public function getType(string $type): int {
		return array(
			"double" => SQLITE3_FLOAT,
			"string" => SQLITE3_TEXT,
			"integer" => SQLITE3_INTEGER,
			"array" => SQLITE3_BLOB,
			"boolean" => SQLITE3_INTEGER,
			"NULL" => SQLITE3_NULL
		)[$type] ?: SQLITE3_TEXT;
	}

	/**
	 * The type a value has to be bound as.
	 *
	 * Text is bound with `sqlite3_bind_text()`, which takes the string as a C
	 * string and so stops at the first NUL byte. A string carrying one has to
	 * go in as a blob or it is stored truncated, with nothing to say so. PHP
	 * puts NUL bytes in ordinary strings more often than it looks:
	 * `serialize()` mangles every protected and private property name with
	 * them, which is what a queued Ticker task is made of.
	 */
	protected function bindType($value): int {
		if (is_string($value) && str_contains($value, "\0"))
			return SQLITE3_BLOB;

		return $this -> getType(gettype($value));
	}

	protected function filePath() {
		return $this -> path . "/" . $this -> file;
	}

	public function connect(array $options) {
		$this -> path = $options["path"];
		$this -> file = (string) ($options["file"] ?? $this -> file);
		$this -> setup();
	}

	public function disconnect(): void {
		if (!$this -> connected)
			return;

		try {
			$this -> instance -> close();
		} catch (\Throwable) {
			// Already gone.
		}

		$this -> connected = false;
		$this -> transactionDepth = 0;
	}

	public function describe(): array {
		return array(
			"driver" => "SQLite3",
			"target" => str_replace("\\", "/", $this -> filePath())
		);
	}

	public function setup() {
		if ($this -> connected)
			return;

		// SQLite will not create the directory holding the database file, and
		// a fresh site has nothing there yet.
		if (!file_exists($this -> path))
			mkdir($this -> path, recursive: true);

		$this -> instance = new \SQLite3($this -> filePath());
		$this -> instance -> enableExceptions(true);
		$this -> connected = true;
		$this -> applyPragmas();
	}

	/**
	 * Put the connection into the shape a web application needs.
	 *
	 * WAL lets a reader and a writer work at the same time, which is what
	 * keeps a request from blocking behind a Ticker runner. The busy timeout
	 * turns the contention that remains into a short wait instead of an
	 * immediate `database is locked`. Foreign keys are off by default in
	 * SQLite and have to be asked for on every connection.
	 */
	protected function applyPragmas(): void {
		$this -> instance -> exec("PRAGMA journal_mode = WAL");
		$this -> instance -> exec("PRAGMA busy_timeout = 5000");
		$this -> instance -> exec("PRAGMA foreign_keys = ON");
	}

	public function reconnect(): void {
		try {
			$this -> instance -> close();
		} catch (\Throwable) {
			// Already closed, which is the usual reason for reconnecting.
		}

		$this -> connected = false;
		$this -> transactionDepth = 0;
		$this -> setup();
	}

	public function raw(string $sql, array $params = array()): array|int {
		$sql = trim($sql);

		try {
			if (empty($params)) {
				$result = $this -> instance -> query($sql);
			} else {
				$stmt = $this -> instance -> prepare($sql);

				foreach (array_values($params) as $index => $value)
					$stmt -> bindValue($index + 1, $value, $this -> bindType($value));

				$result = $stmt -> execute();
			}
		} catch (\Throwable $e) {
			throw new QueryException(
				$this -> instance -> lastErrorCode(),
				$this -> instance -> lastErrorMsg(),
				sql: $sql,
				params: $params
			);
		}

		if ($result === false)
			return 0;

		// A statement with no result set still hands back a result object, so
		// the column count is what tells the two apart.
		if ($result -> numColumns() > 0) {
			$rows = array();

			while ($row = $result -> fetchArray(SQLITE3_ASSOC))
				$rows[] = (object) $row;

			$result -> finalize();
			return $rows;
		}

		$result -> finalize();
		return $this -> instance -> changes();
	}

	public function isUniqueViolation(\Throwable $e): bool {
		// SQLite reports every constraint failure as code 19, so the message
		// is the only thing that says which constraint it was.
		if (static::driverCode($e) !== 19)
			return false;

		return str_contains(strtoupper(static::driverMessage($e)), "UNIQUE");
	}

	public function isConnectionLost(\Throwable $e): bool {
		// The database is a local file held open for the life of the process.
		return false;
	}

	public function tables(): array {
		$rows = $this -> raw(
			"SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
		);

		return array_map(fn ($row) => $row -> name, $rows);
	}

	protected function startTransaction(): void {
		$this -> instance -> exec("BEGIN");
	}

	protected function commitTransaction(): void {
		$this -> instance -> exec("COMMIT");
	}

	protected function rollbackTransaction(): void {
		$this -> instance -> exec("ROLLBACK");
	}

	/**
	 * Dig the engine's own error number out of whatever wrapper it arrived in.
	 */
	protected static function driverCode(\Throwable $e): ?int {
		if ($e instanceof QueryException)
			return isset($e -> data["code"]) ? (int) $e -> data["code"] : null;

		return (int) $e -> getCode();
	}

	/**
	 * The wording the engine used, rather than the description blink wrapped
	 * it in.
	 */
	protected static function driverMessage(\Throwable $e): string {
		if ($e instanceof QueryException)
			return (string) ($e -> data["message"] ?? "");

		return $e -> getMessage();
	}

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
	public function execute(
		string $sql,
		array $params = null,
		int $from = 0,
		int $limit = 0
	): object|array|int {
		$sql = static::cleanSQL($sql);

		// Detect current mode
		$mode = null;
		foreach ([ SQL_SELECT, SQL_INSERT, SQL_UPDATE, SQL_DELETE, SQL_TRUNCATE, SQL_CREATE ] as $m) {
			if (str_starts_with($sql, $m)) {
				$mode = $m;
				break;
			}
		}

		if (empty($mode))
			throw new CodingError("\$DB -> execute(): cannot detect sql execute mode");

		$from = max($from, 0);
		$limit = max($limit, 0);

		if ($from || $limit) {
			if ($mode !== SQL_SELECT)
				throw new CodingError("\$DB -> execute(): \$from and \$limit can only be used in SELECT mode!");

			if ($limit < 1)
				$limit = "18446744073709551615";
			
			$sql .= " LIMIT $limit OFFSET $from";
		}

		try {
			$stmt = $this -> instance -> prepare($sql);
		} catch (\Throwable $e) {
			if ($this -> instance -> lastErrorCode() === 1) {
				// Table not found error.
				$list = explode(" ", $e -> getMessage());
				$table = $list[count($list) - 1];

				throw new TableNotFound($table, $sql);
			}

			throw new QueryException(
				$this -> instance -> lastErrorCode(),
				$this -> instance -> lastErrorMsg(),
				sql: $sql,
				params: $params
			);
		}

		if (!empty($params)) {
			foreach ($params as $index => $value) {
				$stmt -> bindValue($index + 1, $value, $this -> bindType($value));
			}
		}

		try {
			$res = $stmt -> execute();
		} catch (\Throwable $e) {
			throw new QueryException(
				$this -> instance -> lastErrorCode(),
				$this -> instance -> lastErrorMsg(),
				sql: $sql,
				params: $params
			);
		}

		if ($mode === SQL_SELECT) {
			$rows = array();

			while ($row = $res -> fetchArray(SQLITE3_ASSOC)) {
				$row = (object) $row;
	
				if (isset($row -> id))
					$row -> id = (int) $row -> id;
	
				$rows[] = $row;
			}

			return $rows;
		}

		$id = null;
		$affected = 0;

		// Return the inserted record id when in insert mode and
		// number of affected rows on update mode.
		switch ($mode) {
			case SQL_INSERT:
				$id = @$this -> instance -> lastInsertRowID();
				break;

			case SQL_UPDATE:
			case SQL_DELETE:
			case SQL_TRUNCATE:
				$affected = @$this -> instance -> changes();
				break;
		}

		$stmt -> close();

		switch ($mode) {
			case SQL_INSERT:
				return $id;

			case SQL_UPDATE:
			case SQL_DELETE:
			case SQL_TRUNCATE:
			case SQL_CREATE:
				return $affected;
			
			default:
				throw new BaseException(UNKNOWN_ERROR, "\$DB -> execute(): Something went really wrong!", 500);
		}
	}

	public function fetchColumns(string $table): array {
		// PRAGMA takes no bound parameters, so the name is quoted into the
		// statement instead. Doubling any embedded quote is what keeps a
		// crafted table name from closing the literal early.
		$quoted = str_replace("\"", "\"\"", $table);
		$rows = $this -> raw("PRAGMA table_info(\"{$quoted}\")");
		$columns = array();

		foreach ($rows as $row) {
			$column = new ColumnInfo($table, $row -> name);
			$column -> position = ((int) $row -> cid) + 1;
			$column -> default = $row -> dflt_value;
			$column -> nullable = ((int) $row -> notnull) === 0;
			$column -> type = static::columnType((string) $row -> type);

			$columns[$column -> column] = $column;
		}

		return $columns;
	}

	/**
	 * Map a declared type onto blink's column types.
	 *
	 * SQLite stores whatever was written in the `CREATE TABLE`, so this looks
	 * for the affinity keywords the engine itself looks for rather than
	 * matching an exact type name.
	 */
	protected static function columnType(string $declared): ColumnType {
		$declared = strtoupper($declared);

		if (str_contains($declared, "INT"))
			return ColumnType::INT;

		if (str_contains($declared, "CHAR"))
			return ColumnType::VARCHAR;

		if (str_contains($declared, "TEXT") || str_contains($declared, "CLOB") || str_contains($declared, "JSON"))
			return ColumnType::TEXT;

		if (str_contains($declared, "REAL") || str_contains($declared, "FLOA")
			|| str_contains($declared, "DOUB") || str_contains($declared, "NUMERIC")
			|| str_contains($declared, "DECIMAL"))
			return ColumnType::FLOAT;

		if (str_contains($declared, "DATE") || str_contains($declared, "TIME"))
			return ColumnType::DATETIME;

		return ColumnType::UNKNOWN;
	}
}
