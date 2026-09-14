<?php

namespace Blink\DB\Driver;

use Blink\DB\ColumnInfo;
use Blink\DB\Enum\ColumnType;
use Blink\DB\Database;
use Blink\DB\Exception\QueryException;
use Blink\Exception\BaseException;
use Blink\Exception\CodingError;

/**
 * MySQLi database driver.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class MySQLi extends Database {

	public \mysqli $mysqli;

	/**
	 * Options the connection was opened with, kept so {@see MySQLi::reconnect()}
	 * can open the same one again.
	 *
	 * @var array
	 */
	protected array $options = array();

	public function family(): string {
		return "mysql";
	}

	public function getType(string $type): string {
		return array(
			"string" => "s",
			"double" => "d",
			"integer" => "i",
			"array" => "b",
			"boolean" => "i",
			"NULL" => "i"
		)[$type] ?: "s";
	}

	public function connect(array $options) {
		if ($this -> connected)
			return;

		$this -> options = $options;

		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		// A zero port means "whatever the driver defaults to", which is what
		// `ini_get("mysqli.default_port")` already answers.
		$port = (int) ($options["port"] ?? 0);

		$this -> mysqli = new \mysqli(
			$options["host"],
			$options["username"],
			$options["password"],
			$options["database"],
			$port > 0 ? $port : null
		);

		$this -> mysqli -> set_charset("utf8mb4");
		$this -> connected = true;
	}

	public function reconnect(): void {
		if (empty($this -> options))
			throw new CodingError("\$DB -> reconnect(): the connection was never opened.");

		try {
			$this -> mysqli -> close();
		} catch (\Throwable) {
			// Already gone, which is the usual reason for reconnecting.
		}

		$this -> connected = false;
		$this -> transactionDepth = 0;
		$this -> connect($this -> options);
	}

	public function raw(string $sql, array $params = array()): array|int {
		$sql = trim($sql);

		try {
			if (empty($params)) {
				// Straight to query(), since prepare() rejects a good part of
				// the DDL Terra emits.
				$result = $this -> mysqli -> query($sql);
			} else {
				list($normalizedSql, $normalizedParams) = $this -> normalizeParams($sql, $params);

				$types = "";
				foreach ($normalizedParams as $value)
					$types .= $this -> getType(gettype($value));

				$stmt = $this -> mysqli -> prepare($normalizedSql);

				if ($stmt === false) {
					throw new QueryException(
						$this -> mysqli -> errno,
						$this -> mysqli -> error,
						state: $this -> mysqli -> sqlstate,
						sql: $sql,
						params: $params
					);
				}

				if (!empty($normalizedParams))
					$stmt -> bind_param($types, ...$normalizedParams);

				$stmt -> execute();
				$result = $stmt -> get_result();
			}
		} catch (\mysqli_sql_exception $e) {
			throw new QueryException(
				$e -> getCode(),
				$e -> getMessage(),
				state: $e -> getSqlState(),
				sql: $sql,
				params: $params
			);
		}

		if ($result instanceof \mysqli_result) {
			$rows = array();

			while ($row = $result -> fetch_array(MYSQLI_ASSOC))
				$rows[] = (object) $row;

			$result -> free();
			return $rows;
		}

		return $this -> mysqli -> affected_rows;
	}

	public function isUniqueViolation(\Throwable $e): bool {
		return in_array(static::driverCode($e), array( 1062, 1169, 1586 ), true);
	}

	public function isConnectionLost(\Throwable $e): bool {
		// 2006 server gone away, 2013 lost connection mid-query, 4031 the
		// server closed an idle connection.
		return in_array(static::driverCode($e), array( 2006, 2013, 4031 ), true);
	}

	public function tables(): array {
		$rows = $this -> raw(
			"SELECT table_name AS name FROM information_schema.TABLES
				WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
		);

		return array_map(fn ($row) => $row -> name, $rows);
	}

	protected function startTransaction(): void {
		$this -> mysqli -> begin_transaction();
	}

	protected function commitTransaction(): void {
		$this -> mysqli -> commit();
	}

	protected function rollbackTransaction(): void {
		$this -> mysqli -> rollback();
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
	 * Execute a SQL query.
	 *
	 * @param	string		$sql	The query
	 * @param	array		$params
	 * @param	int			$from
	 * @param	int			$limit
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

			$sql .= " LIMIT $from, $limit";
		}

		$normalizedSql = $sql;
		$normalizedParams = null;

		if (!empty($params))
			[$normalizedSql, $normalizedParams] = $this -> normalizeParams($sql, $params);

		// Generate types string.
		$types = "";
		if (!empty($normalizedParams)) {
			foreach ($normalizedParams as $value)
				$types .= $this -> getType(gettype($value));
		}

		try {
			$stmt = $this -> mysqli -> prepare($normalizedSql);
		} catch (\mysqli_sql_exception $e) {
			throw new QueryException(
				$e -> getCode(),
				$e -> getMessage(),
				state: $e -> getSqlState(),
				sql: $sql,
				params: $params
			);
		}

		if ($stmt === false) {
			throw new QueryException(
				$this -> mysqli -> errno,
				$this -> mysqli -> error,
				state: $this -> mysqli -> sqlstate,
				sql: $sql,
				params: $params
			);
		}

		if (!empty($normalizedParams))
			$stmt -> bind_param($types, ...$normalizedParams);

		$stmt -> execute();

		// Check for error
		if ($stmt -> errno) {
			throw new QueryException(
				$this -> mysqli -> errno,
				$this -> mysqli -> error,
				state: $this -> mysqli -> sqlstate,
				sql: $sql,
				params: $params
			);
		}

		$res = $stmt -> get_result();
		if (!is_bool($res)) {
			$rows = array();

			while ($row = $res -> fetch_array(MYSQLI_ASSOC)) {
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
				$id = @$this -> mysqli -> insert_id;
				break;

			case SQL_UPDATE:
			case SQL_DELETE:
			case SQL_TRUNCATE:
				$affected = @$this -> mysqli -> affected_rows;
				break;
		}

		$stmt -> close();

		switch ($mode) {
			case SQL_INSERT:
				return $id;

			case SQL_UPDATE:
			case SQL_DELETE:
			case SQL_TRUNCATE:
				return $affected;

			default:
				throw new BaseException(UNKNOWN_ERROR, "\$DB -> execute(): Something went really wrong!", 500);
		}
	}

	public function fetchColumns(string $table): array {
		// The schema filter matters: `information_schema` spans every database
		// on the server, so without it a table of the same name elsewhere
		// contributes its columns too.
		//
		// Every column is aliased because the case `information_schema` hands
		// back varies between server versions, and an alias is the one thing
		// that pins it.
		$sql = "SELECT table_name AS `table`, column_name AS `column`, ordinal_position AS `position`,
					column_default AS `default`, is_nullable AS `nullable`, data_type AS `type`
				FROM information_schema.COLUMNS
				WHERE table_schema = DATABASE() AND table_name = :name
				ORDER BY ordinal_position";

		$results = $this -> execute($sql, array(
			"name" => $table
		));

		$columns = array();

		foreach ($results as $result) {
			$column = new ColumnInfo($result -> table, $result -> column);
			$column -> position = intval($result -> position);
			$column -> default = $result -> default;
			// `nullable` is the string "YES" or "NO", and both are truthy.
			$column -> nullable = (strtoupper((string) $result -> nullable) === "YES");
			$column -> type = match (strtolower($result -> type)) {
				"char", "varchar" => ColumnType::VARCHAR,
				"tinytext", "text", "mediumtext", "longtext", "json" => ColumnType::TEXT,
				"tinyint", "smallint", "mediumint", "int", "bigint" => ColumnType::INT,
				"float", "double", "decimal" => ColumnType::FLOAT,
				"date", "datetime", "timestamp" => ColumnType::DATETIME,
				default => ColumnType::UNKNOWN
			};

			$columns[$column -> column] = $column;
		}

		return $columns;
	}
}
