<?php

namespace Blink;

use Blink\Exception\CodingError;
use Blink\Query\Builder;
use Blink\Query\Condition;

/**
 * Query.php
 * 
 * Database Query Builder.
 * 
 * @template	G
 * @extends		Builder<Query<G>>
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
final class Query extends Builder {
	const JOIN_LEFT = "LEFT JOIN";
	const JOIN_RIGHT = "RIGHT JOIN";
	const JOIN_INNER = "INNER JOIN";

	/**
	 * Object class name.
	 *
     * @var	string
     * @internal
     * @psalm-var class-string<G>
     */
	protected String $class;

	/**
	 * Table name of this query.
	 * @var string
	 */
	protected String $table;

	/**
	 * Fillable keys from target class.
	 * @var array
	 */
	protected Array $fillables;

	/**
	 * List of select fields.
	 * @var string
	 */
	protected Array $selects = Array();

	/**
	 * List of groups condition.
	 * @var string
	 */
	protected Array $groupBy = Array();

	/**
	 * Order to sort the results in.
	 * @var string[]
	 */
	protected Array $sortBy = Array();

	/**
	 * Set values for update statement.
	 * @var string[]
	 */
	protected Array $sets = Array();

	/**
	 * Raw SQL call for this query.
	 * If set, this will be run instead of the query built with other APIs.
	 * @var ?string
	 */
	protected ?String $sql = null;

	/**
	 * Params used to call the raw SQL command.
	 * @var array
	 */
	protected ?Array $sqlParams = Array();

	/**
	 * List of joins condition.
	 * @var string
	 */
	protected Array $joins = Array();

	/**
	 * List of join param values.
	 * @var string
	 */
	protected Array $joinValues = Array();

	/**
	 * Context role.
	 * @var ?int
	 */
	protected ?int $contextRole = null;

	/**
	 * Context perm.
	 * @var ?int
	 */
	protected ?int $contextPerm = null;

	/**
	 * Permission's path string.
	 * @var ?int
	 */
	protected ?String $permissionName = null;

	/**
	 * Permission's perm bitmask.
	 * @var ?int
	 */
	protected ?int $permissionPerm = null;

	/**
	 * Create a new query.
	 *
	 * @param	class-string<G>		$class		Class to create new instance to.
	 * @param	string				$table		Table name to fetch data from.
	 */
	public function __construct(String $class, String $table) {
		$this -> class = $class;
		$this -> table = $table;

		if ($this -> isModelQuery()) {
			$class::normalizeMaps();
			$this -> fillables = array_values($class::$fillables);
		}
	}

	protected function isModelQuery() {
		return ($this -> class !== "stdClass" && $this -> class !== DB::class);
	}

	/**
	 * Initialiate this query in raw SQL mode.
	 * All other API call to build query will be ignored.
	 *
	 * @param	string	$sql		Raw SQL command.
	 * @param	array	$params		SQL params.
	 * @return	$this
	 */
	public function sql(String $sql, Array $params = Array()): Query {
		$this -> sql = $sql;
		$this -> sqlParams = $params;

		return $this;
	}

	/**
	 * Perform a join from another table.
	 *
	 * @param	string	$type			Join type.
	 * @param	string	$table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @param	array	$args			Call argument.
	 * @return	$this
	 */
	protected function processJoin(
		String $table,
		Array $args,
		String $type = Query::JOIN_INNER
	): Query {
		$builder = new Builder();

		if (count($args) === 3) {
			$builder -> where($args[0], $args[1], $args[2]);
		} else if (count($args) === 2) {
			$builder -> where($args[0], "=", $args[1]);
		} else if (count($args) === 1 && is_callable($args[0])) {
			$args[0]($builder);
		}

		list($query, $params) = $builder -> build();

		if (!empty($query)) {
			$this -> joins[] = "{$type} {{$table}} ON ({$query})";
			$this -> joinValues = array_merge($this -> joinValues, $params);
		}

		return $this;
	}

	/**
	 * Perform an inner join from another table.
	 *
	 * @param	string	$table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	$this
	 */
	public function join(String $table, ...$args) {
		return $this -> processJoin($table, $args, static::JOIN_INNER);
	}

	/**
	 * Perform a left join from another table.
	 *
	 * @param	string	$table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	$this
	 */
	public function leftJoin(String $table, ...$args): Query {
		return $this -> processJoin($table, $args, static::JOIN_LEFT);
	}

	/**
	 * Perform a right join from another table.
	 *
	 * @param	string	$table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	$this
	 */
	public function rightJoin(String $table, ...$args): Query {
		return $this -> processJoin($table, $args, static::JOIN_RIGHT);
	}

	/**
	 * Add field select to this query.
	 *
	 * @param	string	...$selects		Select fields to add.
	 * @return	$this
	 */
	public function select(String ...$selects): Query {
		foreach ($selects as &$select) {
			$sVal = Condition::validateColumnValue($select);

			if ($sVal) {
				list($sTable, $sColumn) = $sVal;
				$select = "{$sTable}.{$sColumn}";
			}
		}

		$this -> selects = array_merge($this -> selects, $selects);
		return $this;
	}

	/**
	 * Parse search query into SQL query, for easy searching
	 * and filtering.
	 *
	 * Use `column(=, >, <, >=, <=)value` (example `column=value`) format to filter with exact value of an column.
	 *
	 * @param	string		$query
	 * @param	string[]	$columns		Columns where normal search query will apply.
	 * @return	$this
	 */
	public function search(String $query, Array $columns) {
		$query = trim($query);

		if (empty($query))
			return $this;

		$group = null;
		$match = null;
		$searchWhere = null;
		$tokens = explode(" ", $query);
		$re = '/^([a-zA-Z0-9-_.]+)(=|>|<|>=|<=|<>)([a-zA-Z0-9]+)$/';

		foreach ($tokens as $token) {
			// Try matching the column value format.
			if (preg_match($re, $token, $match)) {
				$valid = false;

				try {
					$validate = Condition::validateColumnValue($match[1]);

					if ($validate)
						$valid = true;
				} catch (\Throwable $e) {
					// We don't need to handle error here.
					continue;
				}

				if (!$valid && $this -> isModelQuery()) {
					$checkName = $this -> class::$table . "." . $match[1];

					try {
						$validate = Condition::validateColumnValue($checkName);

						if ($validate) {
							// Use the new column name to prevent ambiguous column.
							$match[1] = $checkName;
							$valid = true;
						}
					} catch (\Throwable $e) {
						// We don't need to handle error here.
						continue;
					}
				}

				if ($valid)
					$this -> where($match[1], $match[2], $match[3]);

				continue;
			}

			if (empty($group) && !empty($columns)) {
				$group = new Builder();
				$colKeys = Array();

				// Build where clause using CONCAT_WS.
				foreach ($columns as $column) {
					if ($this -> isModelQuery()) {
						if (!str_contains($column, ".")) {
							// Apply current table name to column.
							$column = $this -> class::$table . ".{$column}";
						}

						$validate = Condition::validateColumnValue($column);

						if ($validate)
							$colKeys[] = $validate[0] . "." . $validate[1];
					} else {
						// We don't need to validate column name in raw mode. Just
						// let the dev do it.
						$colKeys[] = $column;
					}
				}

				$searchWhere = "CONCAT_WS(' ', " . implode(", ", $colKeys) . ")";
			}

			foreach ($columns as $column)
				$group -> where($searchWhere, "LIKE", "%{$token}%");
		}

		if (!empty($group))
			$this -> conditions[] = $group;

		return $this;
	}

	/**
	 * Groups rows that have the same values into summary rows.
	 *
	 * @param	string|string[]		$by		Column name to group by.
	 * @return	$this
	 */
	public function group(String|Array $by) {
		if (is_array($by))
			$this -> groupBy = array_merge($this -> groupBy, $by);
		else
			$this -> groupBy[] = $by;

		return $this;
	}

	/**
	 * Set order by for this query.
	 * This is shorthand of `Query::order()`
	 *
	 * @param	string		$by			Column name to sort by.
	 * @param	string		$direction	Direction to sort by.
	 * @return	$this
	 */
	public function sort(String $by, String $direction = "ASC") {
		return $this -> order($by, $direction);
	}

	/**
	 * Set order by for this query.
	 *
	 * @param	string		$by			Column name to sort by.
	 * @param	string		$direction	Direction to sort by.
	 * @return	$this
	 */
	public function order(String $by, String $direction = "ASC") {
		$this -> sortBy[$by] = $direction;
		return $this;
	}

	/**
	 * Set order by for this query.
	 * Only work with `update()`!
	 *
	 * @param	string		$column		Column name to change value
	 * @param	string		$column		The new value
	 * @return	$this
	 */
	public function set(String $column, $value) {
		$this -> sets[$column] = $value;
		return $this;
	}

	protected function makeSQLCall(?Array $selects = null) {
		if (!empty($this -> sql))
			return Array( $this -> sql, $this -> sqlParams );

		// Start generating selects string.
		if (!empty($selects)) {
			// Select in function args has most priority.
			$selects = implode(", ", $selects);
		} else if (!empty($this -> fillables)) {
			$table = $this -> table;

			$selects = array_map(function ($i) use ($table) {
				return "{$table}.{$i}";
			}, $this -> fillables);

			$selects = implode(", ", $selects);
		} else if (!empty($this -> selects)) {
			// Use selects defined with `-> select()`
			$selects = implode(", ", $this -> selects);
		} else {
			// Raw query mode. Select all available columns.
			$selects = "*";
		}

		list($where, $params) = parent::build();
		$joins = "";

		if (!empty($this -> joins)) {
			$joins = implode("\n", $this -> joins);
			$params = array_merge($this -> joinValues, $params);
		}

		if (!empty($where))
			$where = "WHERE ({$where})";

		$sql = "SELECT {$selects} FROM {$this -> table}
				{$joins}
				{$where}";

		foreach ($this -> groupBy as $by) {
			$gVal = Condition::validateColumnValue($by);

			if ($gVal) {
				list($gTable, $gCol) = $gVal;
				$sql .= "\nGROUP BY {$gTable}.{$gCol}";
			} else {
				$sql .= "\nGROUP BY {$by}";
			}
		}

		if (!empty($this -> sortBy)) {
			$bys = Array();

			foreach ($this -> sortBy as $by => $direction) {
				$sVal = Condition::validateColumnValue($by);

				if ($sVal) {
					list($sTable, $sCol) = $sVal;
					$bys[] = "{$sTable}.{$sCol} {$direction}";
				} else {
					if (!str_contains($by, ".") && $this -> isModelQuery()) {
						// Try adding model table name to validate again.
						$by = $this -> class::$table . ".{$by}";
						$sVal = Condition::validateColumnValue($by);

						if ($sVal) {
							list($sTable, $sCol) = $sVal;
							$bys[] = "{$sTable}.{$sCol} {$direction}";
						} else {
							$bys[] = "{$by} {$direction}";
						}
					} else {
						// By directive might have exact table column name, just add it to
						// query to avoid confusion.
						$bys[] = "{$by} {$direction}";
					}
				}
			}

			$sql .= "\nORDER BY " . implode(", ", $bys);
		}

		return Array( $sql, $params );
	}

	/**
	 * Fetch the first record and return it.
	 *
	 * @return ?G
	 */
	public function first() {
		global $DB;

		list($sql, $params) = $this -> makeSQLCall();

		if (empty($sql))
			return null;

		$record = $DB -> execute($sql, $params, 0, 1);
		$record = reset($record);

		if (empty($record))
			return null;

		if (!$this -> isModelQuery())
			return $record;

		return $this -> class::processRecord($record);
	}

	/**
	 * Check if any records that match the condition.
	 *
	 * @return bool
	 */
	public function exists() {
		global $DB;

		list($sql, $params) = $this -> makeSQLCall([ "'x'" ]);

		if (empty($sql))
			return false;

		$result = $DB -> execute($sql, $params);
		return !empty($result);
	}

	/**
	 * Delete records that match the condition.
	 *
	 * @return bool
	 */
	public function delete() {
		global $DB;

		list($where, $params) = parent::build();

		if (empty($where))
			return false;

		$sql = "DELETE FROM {$this -> table} WHERE {$where}";
		$DB -> execute($sql, $params);

		return true;
	}

	/**
	 * Truncate the table entirely.
	 *
	 * @return bool
	 */
	public function truncate() {
		global $DB;
		return $DB -> execute("TRUNCATE TABLE {$this -> table}");
	}

	/**
	 * Fetch all the matching records and return it.
	 *
	 * @param	$from		Return a subset of records, starting at this point.
	 * @param	$count		Return a subset comprising this many records in total.
	 * @return	G[]
	 */
	public function all(int $from = 0, int $count = 0) {
		global $DB;

		$instances = Array();

		list($sql, $params) = $this -> makeSQLCall();

		if (empty($sql))
			return Array();

		$records = $DB -> execute($sql, $params, $from, $count);

		if (!$this -> isModelQuery())
			return $records;

		foreach ($records as $record)
			$instances[] = $this -> class::processRecord($record);

		return $instances;
	}

	/**
	 * Count all the matching records and return the amount of it.
	 *
	 * @param	string	$column
	 * @param	bool	$distinct	Select distinct.
	 * @param	bool	$over		Add `OVER()` to select count query.
	 * @return	int
	 */
	public function count(String $column = "x", bool $distinct = false, bool $over = false) {
		global $DB;

		if ($column !== "x" && str_contains($column, ".")) {
			list($table, $column) = Condition::validateColumnValue($column);
			$column = "{$table}.{$column}";
		} else if ($column !== "*") {
			$column = "'{$column}'";
		}

		if ($distinct)
			$column = "DISTINCT {$column}";

		$select = "COUNT({$column})";

		if ($over)
			$select = "{$select} OVER()";

		list($sql, $params) = $this -> makeSQLCall([ $select ]);

		if (empty($sql))
			return 0;

		$record = (Array) $DB -> execute($sql, $params, 0, 1);
		$record = reset($record);
		return (int) $record;
	}

	/**
	 * Execute an update statement to update values in database.
	 * This is to be used with `set()`.
	 *
	 * @return	bool
	 */
	public function update() {
		global $DB;

		$params = Array();
		$sets = array_map(function ($k, $v) use (&$params) {
			$params[] = $v;
			return "{$k} = ?";
		}, array_keys($this -> sets), $this -> sets);

		$sql = "UPDATE {{$this -> table}}\n"
			. "SET " . implode(", ", $sets);

		list($where, $wparams) = parent::build();

		if (!empty($where)) {
			$sql .= "\nWHERE {$where}";
			$params = array_merge($params, $wparams);
		}

		$this -> sql($sql, $params);
		return $this -> exec();
	}

	/**
	 * Execute the raw SQL set with `sql()` or `raw()`.
	 *
	 * @return	bool
	 */
	public function exec() {
		global $DB;

		if (empty($this -> sql))
			throw new CodingError(static::class . " -> exec(): exec can only be used in raw mode.");

		return $DB -> execute($this -> sql, $this -> sqlParams);
	}
}
