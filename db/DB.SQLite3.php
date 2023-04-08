<?php

namespace Blink\DB;

use Blink\DB;
use Blink\DB\Exception\DatabaseNotUpgraded;
use Blink\DB\Exception\SQLError;
use Blink\DB\Exception\TableNotFound;
use Blink\Exception\BaseException;
use Blink\Exception\CodingError;

/**
 * DB.SQLite3.php
 * 
 * SQLite3 database driver.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class SQLite3 extends DB {
	/**
	 * Database instance
	 * @var SQLite3
	 */
	public $instance;
	
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

	/**
	 * Current DB version.
	 * @var int
	 */
	public $version = 0;

	/**
	 * Table structures version.
	 * @var int
	 */
	public $tableVersion = 0;

	public function getType(String $type): int {
		return Array(
			"double" => SQLITE3_FLOAT,
			"string" => SQLITE3_TEXT,
			"integer" => SQLITE3_INTEGER,
			"array" => SQLITE3_BLOB,
			"boolean" => SQLITE3_INTEGER,
			"NULL" => SQLITE3_NULL
		)[$type] ?: SQLITE3_TEXT;
	}

	protected function filePath() {
		return $this -> path . "/" . $this -> file;
	}

	public function dbVersion(): int {
		if ($this -> version !== 0)
			return $this -> version;

		$content = fileGet("{$this -> path}/database.version");

		if ($content == false)
			return 0;

		$this -> version = intval($content);
		return $this -> version;
	}

	public function tbVersion(): int {
		if ($this -> tableVersion !== 0)
			return $this -> tableVersion;
		
		$content = fileGet("{$this -> path}/tables/.version");

		if ($content == false)
			return 0;

		$this -> tableVersion = intval($content);
		return intval($content);
	}

	public function needUpgrade(): bool {
		return $this -> dbVersion() < $this -> tbVersion();
	}

	public function upgrade(int $version) {
		filePut("{$this -> path}/database.version", $version);
		$this -> version = $version;
	}

	public function connect(Array $options) {
		$this -> path = $options["path"];
		$this -> setup();
	}

	public function setup() {
		if ($this -> connected)
			return;

		if (!file_exists($this -> filePath())) {
			$this -> init();
			return;
		}

		$this -> instance = new \SQLite3($this -> filePath());
		$this -> instance -> enableExceptions(true);
		$this -> dbVersion();
		$this -> connected = true;

		if (file_exists("{$this -> path}/upgrade.php") && $this -> needUpgrade()) {
			/**
			 * SQLite3 instance, only available when performing
			 * an database upgrade!
			 * @var \Blink\DB\SQLite3
			 */
			global $SQLiDB;
			$SQLiDB = $this;

			require_once "{$this -> path}/upgrade.php";

			// Check if we still need to upgrade.
			if ($this -> needUpgrade())
				throw new DatabaseNotUpgraded($this -> dbVersion(), $this -> tbVersion());
		}
	}

	/**
	 * Initialize Database
	 */
	public function init() {
		$this -> instance = new \SQLite3($this -> filePath());
		$this -> instance -> enableExceptions(true);

		$tables = glob($this -> path . "/tables/*.sql");
		foreach ($tables as $table) {
			$content = (new \FileIO($table)) -> read();

			if (empty($content))
				continue;

			if (!$this -> instance -> exec($content)) {
				throw new SQLError(
					$this -> instance -> lastErrorCode(),
					$this -> instance -> lastErrorMsg(),
					$content
				);
			}
		}

		// Upgrade current version
		$this -> upgrade($this -> tbVersion());
	}

	/**
	 * Execute a SQL query.
	 * 
	 * @param	string		$sql	The query
	 * @param	array		$params
	 * @param	int			$from
	 * @param	int			$limit
	 * @return	object|array|int	Array of rows object in select mode, inserted record
	 * 								id in insert mode, and number of affected row
	 * 								in update mode.
	 */
	public function execute(
		String $sql,
		Array $params = null,
		int $from = 0,
		int $limit = 0
	): Object|Array|int {
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

			throw new SQLError(
				$this -> instance -> lastErrorCode(),
				$this -> instance -> lastErrorMsg(),
				$sql
			);
		}

		if (!empty($params)) {
			foreach ($params as $key => $value) {
				$type = $this -> getType(gettype($value));
				$stmt -> bindValue($key + 1, $value, $type);
			}
		}

		try {
			$res = $stmt -> execute();
		} catch (\Throwable $e) {
			throw new SQLError(
				$this -> instance -> lastErrorCode(),
				$this -> instance -> lastErrorMsg(),
				$sql
			);
		}

		if ($mode === SQL_SELECT) {
			$rows = Array();

			while ($row = $res -> fetchArray(SQLITE3_ASSOC)) {
				$row = (Object) $row;
	
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
}