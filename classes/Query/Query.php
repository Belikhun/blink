<?php

namespace Blink;

use Blink\Exception\CodingError;
use Blink\Query\Builder;
use Blink\Query\Condition;
use Blink\Query\QueryBuilder;

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
class Query extends Builder {
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
	 *
	 * @var string|Query
	 */
	protected String|Query $table;

	/**
	 * All registered table name aliases of this query.
	 *
	 * @var string[]
	 */
	protected Array $aliases = Array();

	/**
	 * Table name alias of this query.
	 *
	 * @var ?int
	 */
	protected ?int $limitFrom = null;

	/**
	 * Table name alias of this query.
	 *
	 * @var int
	 */
	protected ?int $limitCount = null;

	/**
	 * Fillable keys from target class.
	 *
	 * @var array
	 */
	protected Array $fillables;

	/**
	 * List of select fields.
	 *
	 * @var string[]|string[][]
	 */
	protected Array $selects = Array();

	/**
	 * List of groups condition.
	 *
	 * @var string[]
	 */
	protected Array $groupBy = Array();

	/**
	 * Order to sort the results in.
	 *
	 * @var string[]
	 */
	protected Array $sortBy = Array();

	/**
	 * Set values for update statement.
	 *
	 * @var string[]
	 */
	protected Array $sets = Array();

	/**
	 * Raw SQL call for this query.
	 * If set, this will be run instead of the query built with other APIs.
	 *
	 * @var ?string
	 */
	protected ?String $sql = null;

	/**
	 * When set to true, the select query will also have the `DISTINCT` statement.
	 * Used to return only distinct (different) values.
	 */
	protected bool $distinct = false;

	/**
	 * Params used to call the raw SQL command.
	 *
	 * @var array
	 */
	protected ?Array $sqlParams = Array();

	/**
	 * List of joins condition.
	 *
	 * @var string[]
	 */
	protected Array $joins = Array();

	/**
	 * List of join param values.
	 *
	 * @var string[]
	 */
	protected Array $joinValues = Array();

	/**
	 * Create a new query.
	 *
	 * @param	class-string<G>		$class		Class to create new instance to.
	 * @param	string|Query		$table		Table name or subquery to fetch data from.
	 */
	public function __construct(String $class, String|Query $table) {
		$this -> class = $class;
		$this -> table = $table;

		if ($this -> isModelQuery()) {
			$class::normalizeMaps();
			$this -> fillables = array_values($class::$fillables);
		}
	}

	/**
	 * Change table to select from.
	 *
	 * @param	string|Query	$table	Table name or subquery to fetch data from.
	 * @param	string			$alias	Table alias.
	 * @return	static<G>
	 */
	public function table(String|Query $table, String $alias = null) {
		$this -> table = $table;
		$this -> alias($alias);
		return $this;
	}

	/**
	 * Set table name alias.
	 *
	 * @param	string	$alias	Table alias.
	 * @return	static<G>
	 */
	public function alias(String $alias = null) {
		$this -> aliases["@this"] = $alias;
		return $this;
	}

	public function getTableName(): String {
		return ($this -> table instanceof Query)
			? $this -> table -> table
			: $this -> table;
	}

	/**
	 * Get model class name for this query.
	 *
	 * @return	class-string<Model>
	 */
	public function getClass(): String {
		return $this -> class;
	}

	public function isModelQuery() {
		return ($this -> class !== "stdClass" && $this -> class !== DB::class);
	}

	/**
	 * Initialiate this query in raw SQL mode.
	 * All other API call to build query will be ignored.
	 *
	 * @param	string	$sql		Raw SQL command.
	 * @param	array	$params		SQL params.
	 * @return	static<G>
	 */
	public function sql(String $sql, Array $params = Array()): Query {
		$this -> sql = $sql;
		$this -> sqlParams = $params;

		return $this;
	}

	/**
	 * Perform a join from another table.
	 *
	 * @param	string	$table		Table name to join with (ex. users).
	 * @param	array	$args		Call argument.
	 * @param	string	$type		Join type.
	 * @return	static<G>
	 */
	protected function processJoin(
		String $table,
		Array $args,
		String $type = Query::JOIN_INNER
	): Query {
		/** @var QueryBuilder */
		$builder = new QueryBuilder();

		if (count($args) === 3) {
			$builder -> where($args[0], $args[1], $args[2]);
		} else if (count($args) === 2) {
			$builder -> where($args[0], "=", $args[1]);
		} else if (count($args) === 1 && is_callable($args[0])) {
			$args[0]($builder);
		}

		list($query, $params) = $builder -> build();

		if (!empty($query)) {
			$this -> joins[] = "{$type} {$table} ON ({$query})";
			$this -> joinValues = array_merge($this -> joinValues, $params);
		}

		return $this;
	}

	/**
	 * Perform an inner join from another table.
	 *
	 * @param	string	$table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	static<G>
	 */
	public function join(String $table, ...$args) {
		return $this -> processJoin($table, $args, static::JOIN_INNER);
	}

	/**
	 * Perform a left join from another table.
	 *
	 * @param	string	$table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	static<G>
	 */
	public function leftJoin(String $table, ...$args): Query {
		return $this -> processJoin($table, $args, static::JOIN_LEFT);
	}

	/**
	 * Perform a right join from another table.
	 *
	 * @param	string	$table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	static<G>
	 */
	public function rightJoin(String $table, ...$args): Query {
		return $this -> processJoin($table, $args, static::JOIN_RIGHT);
	}

	/**
	 * Add field select to this query.
	 *
	 * @param	string|array	...$selects		Select fields to add.
	 * @return	static<G>
	 */
	public function select(String|Array ...$selects): Query {
		foreach ($selects as &$select) {
			$col = (is_array($select))
				? $select[0]
				: $select;

			$sVal = Condition::validateColumnValue($col);

			if ($sVal) {
				list($sTable, $sColumn) = $sVal;
				$col = "{$sTable}.{$sColumn}";
			}

			if (is_array($select))
				$select[0] = $col;
			else
				$select = $col;
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
	 * @return	static<G>
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
				$group = new QueryBuilder();
				$colKeys = Array();

				// Build where clause using CONCAT_WS.
				foreach ($columns as $column) {
					if (!str_contains($column, ".")) {
						// Apply current table name to column.
						$column = $this -> class::$table . ".{$column}";
					}

					$validate = Condition::validateColumnValue($column);

					if ($validate)
						$colKeys[] = $validate[0] . "." . $validate[1];
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
	 * Perform a select distinct that return only distinct (different) values.
	 *
	 * @param	bool		$distinct
	 * @return	static<G>
	 */
	public function distinct(bool $distinct = true) {
		$this -> distinct = $distinct;
		return $this;
	}

	/**
	 * Groups rows that have the same values into summary rows.
	 *
	 * @param	string		$by		Column name to group by.
	 * @return	static<G>
	 */
	public function group(String ...$by) {
		$this -> groupBy = array_merge($this -> groupBy, $by);
		return $this;
	}

	/**
	 * Set order by for this query.
	 * This is shorthand of `Query::order()`
	 *
	 * @param	string		$by			Column name to sort by.
	 * @param	string		$direction	Direction to sort by.
	 * @return	static<G>
	 */
	public function sort(String $by, String $direction = "ASC") {
		return $this -> order($by, $direction);
	}

	/**
	 * Set order by for this query.
	 *
	 * @param	string		$by			Column name to sort by.
	 * @param	string		$direction	Direction to sort by.
	 * @return	static<G>
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
	 * @return	static<G>
	 */
	public function set(String $column, $value) {
		$this -> sets[$column] = $value;
		return $this;
	}

	/**
	 * Limit number of records to be returned in the query.
	 *
	 * @param	int			$from
	 * @param	int			$count
	 * @return	static<G>
	 */
	public function limit(int $from = 0, int $count = 0) {
		$this -> limitFrom = $from;
		$this -> limitCount = $count;

		return $this;
	}

	public function makeSQLCall(?Array $selects = null, bool $limit = true) {
		if (!empty($this -> sql))
			return Array( $this -> sql, $this -> sqlParams );

		// Start generating selects string.
		if (!empty($selects)) {
			// Select in function args has most priority.
			$selects = implode(", ", $selects);
		} else if (!empty($this -> selects)) {
			// Use selects defined with `-> select()`
			// Process select AS first.
			$selects = Array();

			foreach ($this -> selects as $select) {
				if (is_array($select)) {
					$selects[] = $select[0] . " AS " . $select[1];
					continue;
				}

				$selects[] = $select;
			}

			$selects = implode(", ", $selects);
		} else if ($this -> table instanceof Query) {
			// Subquery mode. Select all available columns.
			$selects = "*";
		} else if (!empty($this -> fillables)) {
			$table = $this -> table;

			$selects = array_map(function ($i) use ($table) {
				return "{$table}.{$i}";
			}, $this -> fillables);

			$selects = implode(", ", $selects);
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

		$from = "";
		if ($this -> table instanceof Query) {
			if (empty($this -> aliases["@this"]))
				throw new CodingError("Table alias is required when using subquery!");

			list($sSql, $sParams) = $this -> table -> makeSQLCall();
			$params = array_merge($sParams, $params);
			$from = "({$sSql})";
		} else {
			$from = "{$this -> table}";
		}

		if (!empty($this -> aliases["@this"]))
			$from .= (" " . $this -> aliases["@this"]);

		$sql = ($this -> distinct)
			? "SELECT DISTINCT"
			: "SELECT";

		$sql = "{$sql} {$selects} FROM {$from}
			{$joins}
			{$where}";

		$groupBy = Array();

		foreach ($this -> groupBy as $by) {
			$gVal = Condition::validateColumnValue($by);

			if ($gVal) {
				list($gTable, $gCol) = $gVal;
				$groupBy[] = "{$gTable}.{$gCol}";
			} else {
				$groupBy[] = $by;
			}
		}

		if (!empty($groupBy))
			$sql .= "\nGROUP BY " . implode(", ", $groupBy);

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

							if (!empty($this -> aliases["@this"]))
								$sTable = $this -> aliases["@this"];

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

		if ($limit) {
			// Process limit if set in query.
			$from = empty($this -> limitFrom) ? 0 : $this -> limitFrom;
			$count = empty($this -> limitCount) ? 0 : $this -> limitCount;

			if ($from || $count) {
				if ($count < 1)
					$count = "18446744073709551615";

				$sql .= "\nLIMIT {$from}, {$count}";
			}
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

		if ($this -> table instanceof Query)
			throw new CodingError("Cannot perform DELETE operation on subquery!");

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

		if ($this -> table instanceof Query)
			throw new CodingError("Cannot perform TRUNCATE operation on subquery!");
		
		return $DB -> execute("TRUNCATE TABLE {$this -> table}");
	}

	/**
	 * Fetch all the matching records and return it.
	 *
	 * @param	int		$from			Return a subset of records, starting at this point.
	 * @param	int		$count			Return a subset comprising this many records in total.
	 * @param	bool	$idIndexed		Make returned array id indexed.
	 * @return	G[]
	 */
	public function all(int $from = 0, int $count = 0, bool $idIndexed = false) {
		global $DB;

		$instances = Array();

		list($sql, $params) = $this -> makeSQLCall(limit: false);

		if (empty($sql))
			return Array();

		$records = $DB -> execute($sql, $params, $from, $count);

		if (!$this -> isModelQuery()) {
			if (!$idIndexed)
				return array_values($records);

			return $records;
		}

		if ($idIndexed) {
			foreach ($records as $record) {
				$record = $this -> class::processRecord($record);
				$instances[$record -> getPrimaryValue()] = $record;
			}
		} else {
			foreach ($records as $record)
				$instances[] = $this -> class::processRecord($record);
		}

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

		list($sql, $params) = $this -> makeSQLCall([ "{$select} AS count" ]);

		if (empty($sql))
			return 0;

		$record = (Array) $DB -> execute($sql, $params, 0, 1);
		$record = reset($record);
		$record = get_object_vars($record);
		return (int) (reset($record));
	}

	/**
	 * Execute an update statement to update values in database.
	 * This is to be used with `set()`.
	 *
	 * @return	bool
	 */
	public function update() {
		global $DB;

		if ($this -> table instanceof Query)
			throw new CodingError("Cannot perform UPDATE operation on subquery!");

		$params = Array();
		$sets = array_map(function ($k, $v) use (&$params) {
			$params[] = $v;
			return "{$k} = ?";
		}, array_keys($this -> sets), $this -> sets);

		$sql = "UPDATE {$this -> table}\n"
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
