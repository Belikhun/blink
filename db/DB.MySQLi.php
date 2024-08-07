<?php

namespace Blink\DB;

use Blink\DB;
use Blink\DB\Exception\SQLError;
use Blink\Exception\BaseException;
use Blink\Exception\CodingError;

/**
 * DB.MySQLi.php
 * 
 * MySQLi database driver.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class MySQLi extends DB {
	/** @var mysqli */
	public $mysqli;

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

		// Generate types string.
		$types = "";
		if (!empty($params)) {
			foreach ($params as $value)
				$types = $types . $this -> getType(gettype($value));
		}

		try {
			$stmt = $this -> mysqli -> prepare($sql);
		} catch (\mysqli_sql_exception $e) {
			throw new SQLError(
				$e -> getCode(),
				$e -> getMessage(),
				$sql
			);
		}

		if ($stmt === false) {
			throw new SQLError(
				$this -> mysqli -> errno,
				$this -> mysqli -> error,
				$sql
			);
		}

		if (!empty($params)) {
			$vals = array_values($params);
			$stmt -> bind_param($types, ...$vals);
		}

		$stmt -> execute();

		// Check for error
		if ($stmt -> errno) {
			throw new SQLError(
				$this -> mysqli -> errno,
				$this -> mysqli -> error,
				$sql
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
}
