<?php

namespace Blink\DB\Driver;

use Blink\DB\ColumnInfo;
use Blink\DB\ColumnType;
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

		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$this -> mysqli = new \mysqli(
			$options["host"],
			$options["username"],
			$options["password"],
			$options["database"]
		);

		$this -> mysqli -> set_charset("utf8mb4");
		$this -> connected = true;
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
		$sql = "SELECT table_name, column_name, ordinal_position, column_default, is_nullable,
					data_type
				FROM information_schema.COLUMNS
				WHERE table_name = :name";

		$results = $this -> execute($sql, array(
			"name" => $table
		));

		$columns = array();

		foreach ($results as $result) {
			$column = new ColumnInfo($result -> table_name, $result -> column_name);
			$column -> position = intval($result -> original_position);
			$column -> default = $result -> column_default;
			$column -> nullable = boolval($result -> is_nullable);
			$column -> type = match ($result -> data_type) {
				"varchar" => ColumnType::VARCHAR,
				"mediumtext" => ColumnType::TEXT,
				"text" => ColumnType::TEXT,
				"int" => ColumnType::INT,
				"float" => ColumnType::FLOAT,
				"datetime" => ColumnType::DATETIME,
				default => ColumnType::UNKNOWN
			};

			$columns[] = $column;
		}

		return $columns;
	}
}
