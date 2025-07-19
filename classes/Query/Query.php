<?php

namespace Blink;

use Exception;
use Blink\Exception\CodingError;
use Blink\Query\Expression\Expr;
use Blink\Query\Expression\MatchAgainst;
use Blink\Query\Expression\PartialColumn;
use Blink\Query\QueryIterator;
use Blink\Query\QueryOrder;
use Blink\Query\Abstract\QueryConditionBuilder;
use Blink\Query\Expression\Column;
use Blink\Query\Expression\JoinTable;
use Blink\Query\Expression\SelectTable;
use Blink\Query\Expression\SelectSubQuery;
use Blink\Query\Interface\Sequelizable;
use Blink\Query\Interface\SequelizableWithAlias;
use Blink\Query\Expression\Table;
use Blink\Query\Expression\SelectColumn;
use Blink\Query\QueryUpdateSet;

/**
 * Base query class for querying model.
 *
 * @template	G
 * @extends		parent<static<G>>
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class Query extends QueryConditionBuilder {

	public const JOIN_LEFT = "LEFT JOIN";

	public const JOIN_RIGHT = "RIGHT JOIN";

	public const JOIN_INNER = "INNER JOIN";

	/**
	 * Perform a random rows selection by assigning each rows with a random number and sort them by it.
	 * This will cost a lot of performance when selecting a huge table.
	 *
	 * @link	https://stackoverflow.com/questions/580639/how-to-randomly-select-rows-in-sql
	 * @var		string
	 */
	public const ORDER_RANDOM = "Q:ORDER_RAND";

	public const VALIDATION_NATIVE = "V:N";

	public const VALIDATION_ALIAS = "V:A";

	public const VALIDATION_SUBQUERY = "V:SQ";

	/**
	 * Reference to this.
	 *
	 * @var Query<G>
	 */
	protected Query $query;

	/**
	 * Object class name.
	 *
     * @var	string
     * @internal
     * @psalm-var class-string<G>
     */
	protected string $class;

	/**
	 * Target table of this query.
	 *
	 * @var SelectTable
	 */
	protected SelectTable $table;

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
	protected array $fillables;

	/**
	 * List of table objects used in this query, mapped by table name/alias.
	 *
	 * @var SelectTable[]|JoinTable[]
	 */
	protected array $tables = array();

	/**
	 * List of column objects used in this query, mapped by column name/alias.
	 *
	 * @var SelectColumn[]
	 */
	protected array $columns = array();

	/**
	 * List of select fields.
	 *
	 * @var SelectColumn[]
	 */
	protected array $selects = array();

	/**
	 * List of aliases used in this query, mapped by table/column object id.
	 *
	 * @var string[]
	 */
	protected array $aliases = array();

	/**
	 * List of table/column object mapped by aliases.
	 *
	 * @var SequelizableWithAlias[]
	 */
	protected array $aliasesMap = array();

	/**
	 * List of using sub-queries.
	 *
	 * @var SelectSubQuery[]
	 */
	protected array $subQueries = array();

	/**
	 * List of grouping columns.
	 *
	 * @var Column[]|Sequelizable[]
	 */
	protected array $groupBy = array();

	/**
	 * Order to sort the results in.
	 *
	 * @var QueryOrder[]
	 */
	protected array $sortBy = array();

	/**
	 * Set values for update statement.
	 *
	 * @var QueryUpdateSet[]
	 */
	protected array $sets = array();

	/**
	 * Raw SQL call for this query.
	 * If set, this will be run instead of the query built with other APIs.
	 *
	 * @var ?string
	 */
	protected ?string $sql = null;

	/**
	 * Params used to call the raw SQL command.
	 *
	 * @var array
	 */
	protected ?array $sqlParams = array();

	/**
	 * List of joins condition.
	 *
	 * @var JoinTable[]
	 */
	protected array $joins = array();

	/**
	 * When set to true, the select query will also have the `DISTINCT` statement.
	 * Used to return only distinct (different) values.
	 */
	protected bool $distinct = false;

	/**
	 * Flag to disable resolving all alias in this query.
	 *
	 * @var bool
	 */
	public bool $disableAlias = false;

	/**
	 * Create a new query.
	 *
	 * @param	class-string<G>					$class		Class to create new instance to.
	 * @param	Query|Table|string|array		$table		Table name or subquery to fetch data from.
	 */
	public function __construct(string $class, Query|Table|string|array $table) {
		$this -> query = $this;
		$this -> class = $class;

		if (!empty($table)) {
			$alias = null;

			if (is_array($table))
				[$table, $alias] = $table;

			$this -> table($table, $alias);
		}

		if ($this -> isModelQuery()) {
			$class::normalizeMaps();
			$this -> fillables = array_values($class::$fillables);
		}
	}

	/**
	 * Return the table this query is targetting to.
	 *
	 * @return	SelectTable
	 */
	public function getQueryingTable() {
		return $this -> table;
	}

	/**
	 * Set target table to perform querying.
	 *
	 * @param	string|Table|Query	$table		Table name or subquery to fetch data from.
	 * @param	string				$alias		Table alias.
	 * @return	static<G>
	 */
	public function table(string|Table|Query $table, string $alias = null) {
		if (is_string($table))
			$table = Table::instance($table);

		$this -> table = new SelectTable($table, $alias);

		if ($table instanceof Table)
			$this -> tables[$table -> getName()] = $this -> table;

		if (!empty($alias))
			$this -> setAlias($this -> table, $alias);

		return $this;
	}

	/**
	 * Set current selecting table's alias.
	 *
	 * @param	string			$alias		Alias name
	 * @return	static<G>
	 */
	public function alias(string $alias) {
		$this -> setAlias($this -> table, $alias);
		return $this;
	}

	/**
	 * Set table/column name alias.
	 *
	 * @param	SequelizableWithAlias	$object		Target object to set alias
	 * @param	string					$alias		Alias name
	 * @return	static<G>
	 */
	public function setAlias(SequelizableWithAlias $object, string $alias) {
		$object -> setAlias($alias);
		$this -> aliases[$object -> getID()] = $alias;
		$this -> aliasesMap[$alias] = $object;

		if ($object -> getID() == $this -> table -> getID())
			$this -> aliases["@this"] = $alias;

		if ($object instanceof SelectTable || $object instanceof JoinTable)
			$this -> tables[$alias] = $object;

		if ($object instanceof SelectColumn)
			$this -> columns[$alias] = $object;

		return $this;
	}

	/**
	 * Get alias name for the given object or object id.
	 *
	 * @param	Sequelizable|string		$object
	 * @return	?string
	 */
	public function getAlias(Sequelizable|string $object = "@this"): ?string {
		if (!is_string($object))
			$object = $object -> getID();

		return $this -> aliases[$object] ?? null;
	}

	/**
	 * Get and return table object, based on table name or table alias.
	 *
	 * @param	string		$table		Table name or alias
	 * @return	Table
	 */
	public function getTable(string $table): Table {
		if (isset($this -> aliasesMap[$table]) && $this -> aliasesMap[$table] instanceof Table)
			return $this -> aliasesMap[$table];

		if (isset($this -> tables[$table]))
			return $this -> tables[$table] -> table;

		return Table::instance($table);
	}

	/**
	 * Get and return column object, based on column name or column alias.
	 *
	 * @param	string					$column		Column name or alias
	 * @return	Column|PartialColumn
	 */
	public function getColumn(string $column): Column|PartialColumn {
		if (isset($this -> aliasesMap[$column]) && $this -> aliasesMap[$column] instanceof Column)
			return $this -> aliasesMap[$column];

		if (isset($this -> columns[$column]))
			return $this -> columns[$column] -> column;

		if (str_contains($column, ".")) {
			list($t, $c) = explode(".", $column);
		} else {
			$t = $this -> table -> table;
			$c = $column;
		}

		return Column::instance($t, $c, fallbackPartial: true);
	}

	public function getObjectByAlias(string $alias): ?Sequelizable {
		if (!empty($this -> aliasesMap[$alias]))
			return $this -> aliasesMap[$alias];

		if (!empty($this -> subQueries[$alias]))
			return $this -> subQueries[$alias];

		return null;
	}

	/**
	 * Validate if specified table name is available to use in this query.
	 *
	 * @param	string			$table
	 * @return	bool|string		Return one of `Query::VALIDATION_`, false otherwise.
	 */
	public function haveTable(string $table): bool|string {
		if (!empty($this -> subQueries[$table]))
			return static::VALIDATION_SUBQUERY;

		if ($alias = $this -> getObjectByAlias($table)) {
			if ($alias instanceof SelectTable || $alias instanceof Table)
				return static::VALIDATION_ALIAS;
		}

		if (!empty($this -> tables[$table]))
			return static::VALIDATION_NATIVE;

		return false;
	}

	/**
	 * Validate if specified column name is available to use in this query.
	 *
	 * @param	string			$column		Column alias, column name or full column address.
	 * @param	?string			$table		Additional table name so we don't have to guess it.
	 * @return	bool|string		Return one of `Query::VALIDATION_`, false otherwise.
	 */
	public function haveColumn(string $column, ?string $table = null) {
		if ($alias = $this -> getObjectByAlias($column)) {
			if ($alias instanceof SelectColumn)
				return static::VALIDATION_ALIAS;
		}

		if (empty($table)) {
			if (str_contains($column, ".")) {
				[$table, $column] = explode(".", $column);
			} else if ($this -> table -> table instanceof Table) {
				$table = $this -> table -> table -> table;
			} else {
				// The table we are selecting is a subquery itself, just pass this column
				// check for now and let the engine decide it's fate.
				return static::VALIDATION_SUBQUERY;
			}
		}

		$tableValidation = $this -> haveTable($table);

		if (!$tableValidation)
			return false;

		if ($tableValidation == static::VALIDATION_SUBQUERY)
			return static::VALIDATION_SUBQUERY;

		try {
			$table = $this -> getTable($table);

			if ($table -> haveColumn($column))
				return static::VALIDATION_NATIVE;
		} catch (Exception $e) {
			// We have failed.
		}

		return false;
	}

	/**
	 * Get model class name for this query.
	 *
	 * @return	class-string<Model>
	 */
	public function getClass(): string {
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
	public function sql(string $sql, array $params = array()): Query {
		$this -> sql = $sql;
		$this -> sqlParams = $params;

		return $this;
	}

	/**
	 * Perform a join from another table.
	 *
	 * @param	Table|Query|string|array	$table		Table name to join with (ex. users).
	 * @param	array						$args		Call argument.
	 * @param	string						$type		Join type.
	 * @return	static<G>
	 */
	protected function processJoin(
		Table|Query|string|array $table,
		array $args,
		string $type = Query::JOIN_INNER
	): Query {
		$alias = null;

		if (is_array($table))
			list($table, $alias) = $table;

		if (is_string($table))
			$table = $this -> getTable($table);

		$join = new JoinTable($table, $alias);
		$join -> type = $type;

		if ($table instanceof Table)
			$this -> tables[$table -> getName()] = $join;

		if ($table instanceof Query) {
			$subQuery = new SelectSubQuery($table, $alias);
			$this -> subQueries[$alias] = $subQuery;
			$this -> setAlias($subQuery, $alias);
		} else {
			if (!empty($alias))
				$this -> setAlias($join, $alias);
		}

		if (count($args) === 3) {
			$join -> condition -> where($args[0], $args[1], $args[2]);
		} else if (count($args) === 2) {
			$join -> condition -> where($args[0], "=", $args[1]);
		} else if (count($args) === 1) {
			if (is_callable($args[0])) {
				$args[0]($join -> condition);
			} else if (is_array($args[0])) {
				$join -> condition -> where($args[0]);
			}
		}

		$this -> joins[] = $join;
		return $this;
	}

	/**
	 * Perform an inner join from another table.
	 *
	 * @param	string|array|Table|Query $table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	static<G>
	 */
	public function join(string|array|Table|Query $table, ...$args) {
		return $this -> processJoin($table, $args, static::JOIN_INNER);
	}

	/**
	 * Perform a left join from another table.
	 *
	 * @param	string|array|Table|Query $table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	static<G>
	 */
	public function leftJoin(string|array|Table|Query $table, ...$args): Query {
		return $this -> processJoin($table, $args, static::JOIN_LEFT);
	}

	/**
	 * Perform a right join from another table.
	 *
	 * @param	string|array|Table|Query $table			Table name to join with, shorthand can be used in table name (ex. users u)
	 * @return	static<G>
	 */
	public function rightJoin(string|array|Table|Query $table, ...$args): Query {
		return $this -> processJoin($table, $args, static::JOIN_RIGHT);
	}

	/**
	 * Add field select to this query.
	 *
	 * @param	Sequelizable|array|string	...$selects		Select fields to add. Syntax: `table.column`, `[table.column, alias]`, `[expr, alias]`, `Column`
	 * @return	static<G>
	 */
	public function select(Sequelizable|array|string ...$selects): Query {
		$entities = array();

		foreach ($selects as $select) {
			$alias = null;

			if (is_array($select))
				list($select, $alias) = $select;

			if (is_string($select))
				$select = $this -> getColumn($select);

			// Must be Column or Sequelizable
			$column = new SelectColumn($select, $alias);

			if ($select instanceof Column)
				$this -> columns[$select -> getName()] = $column;

			if (!empty($alias))
				$this -> setAlias($column, $alias);

			$entities[] = $column;
		}

		$this -> selects = array_merge($this -> selects, $entities);
		return $this;
	}

	/**
	 * Parse search query into SQL query, for easy searching
	 * and filtering.
	 *
	 * Default search query will use *string like* search.
	 *
	 * | ⚠ To be able to use fulltext search, all participating columns MUST have `FULLTEXT` index enabled.
	 *
	 * Use `column(=, >, <, >=, <=)value` (example `column=value`) format to filter with exact value of an column.
	 *
	 * @param	string				$query
	 * @param	string[]|Column[]	$columns		Columns where normal search query will apply.
	 * @param	bool				$fulltext		Perform fulltext search. All columns MUST have `FULLTEXT` index enabled.
	 * @return	static<G>
	 * @see		https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html
	 */
	public function search(string $query, array $columns, bool $fulltext = false) {
		$query = trim($query);

		if (empty($query))
			return $this;

		foreach ($columns as &$column) {
			if (is_string($column))
				$column = $this -> getColumn($column);
		}

		$group = null;
		$match = null;
		$searchWhere = null;
		$tokens = explode(" ", $query);
		$leftover = array();
		$re = '/^([a-zA-Z0-9-_.]+)(=|>|<|>=|<=|<>)([a-zA-Z0-9]+)$/';

		foreach ($tokens as $token) {
			// Try matching the column value format.
			if (preg_match($re, $token, $match)) {
				$left = $this -> getColumn($match[1]);
				$comparator = $match[2];
				$right = Expr::value($match[3]);
				$this -> where($left, $comparator, $right);

				continue;
			}

			if ($fulltext) {
				$leftover[] = $token;
				continue;
			}

			if (empty($group))
				$group = $this -> builder();

			if (empty($searchWhere))
				$searchWhere = Expr::lower(Expr::concatWs(Expr::value(" "), ...$columns));

			$group -> where($searchWhere, "LIKE", "%" . mb_strtolower($token) . "%");
		}

		if ($fulltext && !empty($leftover)) {
			if (empty($group))
				$group = $this -> builder();

			$group -> where(new MatchAgainst($columns, implode(" ", $leftover)), ">", 0);
		}

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
	 * @param	Column|Sequelizable|string		$columns		Columns to group by.
	 * @return	static<G>
	 */
	public function group(Column|Sequelizable|string ...$columns) {
		foreach ($columns as &$column) {
			if (is_string($column))
				$column = $this -> getColumn($column);
		}

		$this -> groupBy = array_merge($this -> groupBy, $columns);
		return $this;
	}

	/**
	 * Set order by for this query.
	 * This is shorthand of `Query::order()`
	 *
	 * @param	Column|Sequelizable|string		$column		Column name to sort by.
	 * @param	string				$direction	Direction to sort by.
	 * @return	static<G>
	 */
	public function sort(Column|Sequelizable|string $column, string $direction = "ASC") {
		return $this -> order($column, $direction);
	}

	/**
	 * Set order by for this query.
	 *
	 * @param	Column|Sequelizable|string		$column		Column name to sort by.
	 * @param	string				$direction	Direction to sort by.
	 * @return	static<G>
	 */
	public function order(Column|Sequelizable|string $column, string $direction = "ASC") {
		$this -> sortBy[] = new QueryOrder($column, $direction);
		return $this;
	}

	/**
	 * Set updating column and new value for update statement.
	 *
	 * Only work with `-> update()`!
	 *
	 * @param	Column|string			$column		Column name to update value for
	 * @param	Sequelizable|mixed		$value		The new value to be set to specified column
	 * @return	static<G>
	 */
	public function set(Column|string $column, $value) {
		if (is_string($column))
			$column = $this -> getColumn($column);

		$this -> sets[] = new QueryUpdateSet(
			$column,
			Expr::processValue($value)
		);

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

	/**
	 * Return the SQL query generated by this Query Builder.
	 *
	 * @return	string
	 */
	public function toSQL() {
		[$sql, $params] = $this -> makeSQLCall();
		return $sql;
	}

	public function makeSQLCall(?array $selects = null, bool $limit = true, bool $distinct = false) {
		// Raw mode
		if (!empty($this -> sql))
			return [$this -> sql, $this -> sqlParams];

		$params = [];


		//* ---------------------------------------------
		//* SELECT
		//* ---------------------------------------------
		$selects = empty($selects)
			? $this -> selects
			: $selects;

		if (!empty($selects)) {
			// Use selects defined with `-> select()`
			// Process select AS first.
			$parts = array();

			foreach ($selects as $select) {
				if (is_string($select))
					$select = $this -> getColumn($select);

				list($s, $p) = $select -> sequelize($this);
				$parts[] = $s;
				$params = array_merge($params, $p);
			}

			$selects = implode(",\n\t", $parts);
		} else if (!empty($this -> fillables)) {
			$selects = array();

			foreach ($this -> fillables as $fill) {
				list($s, $p) = $this
					-> getColumn($fill)
					-> sequelize($this);

				$selects[] = $s;
				$params = array_merge($params, $p);
			}

			$selects = implode(",\n\t", $selects);
		} else {
			// Raw query/subquery mode. Select all available columns.
			$selects = "*";
		}


		//* ---------------------------------------------
		//* FROM
		//* ---------------------------------------------
		list($from, $tableParams) = $this -> table -> sequelize($this);
		$params = array_merge($params, $tableParams);

		$sql = ($this -> distinct || $distinct)
			? "SELECT DISTINCT"
			: "SELECT";

		$sql = "{$sql} {$selects}\nFROM {$from}";


		//* ---------------------------------------------
		//* JOINS
		//* ---------------------------------------------
		if (!empty($this -> joins)) {
			$joins = [];

			foreach ($this -> joins as $join) {
				list($s, $p) = $join -> sequelize($this);
				$joins[] = $s;
				$params = array_merge($params, $p);
			}

			$joins = implode("\n", $joins);
			$sql .= "\n{$joins}";
		}


		//* ---------------------------------------------
		//* WHERE
		//* ---------------------------------------------
		list($where, $queryParams) = parent::sequelize($this);
		$params = array_merge($params, $queryParams);
		$joins = "";

		if (!empty($where))
			$sql .= "\nWHERE {$where}";


		//* ---------------------------------------------
		//* GROUP
		//* ---------------------------------------------
		$groupBy = array();

		foreach ($this -> groupBy as $by) {
			[$s, $p] = $by -> sequelize($this);
			$groupBy[] = $s;
			$params = array_merge($params, $p);
		}

		if (!empty($groupBy))
			$sql .= "\nGROUP BY " . implode(", ", $groupBy);


		//* ---------------------------------------------
		//* SORT
		//* ---------------------------------------------
		if (!empty($this -> sortBy)) {
			$bys = array();

			foreach ($this -> sortBy as $by) {
				[$s, $p] = $by -> sequelize($this);
				$bys[] = $s;
				$params = array_merge($params, $p);
			}

			$sql .= "\nORDER BY " . implode(", ", $bys);
		}


		//* ---------------------------------------------
		//* LIMIT
		//* ---------------------------------------------
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

		return [$sql, $params];
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
	 * Return the first found record, or else, create a new record.
	 *
	 * @param	callable|array|null		$fill	Values to fill into the model instance when we start creating it.
	 * @return	G
	 */
	public function firstOrCreate(callable|array|object $fill = null) {
		global $DB;

		if ($this -> table -> table instanceof Query)
			throw new CodingError("Cannot perform <code>Query::firstOrCreate()</code> on virtual table/subquery!");

		$table = $this -> table -> table -> getName();
		$instance = $this -> first();

		if (!empty($instance))
			return $instance;

		if (!empty($fill) && is_callable($fill))
			$fill = $fill();

		if ($fill !== null && is_array($fill))
			$fill = (object) $fill;

		if (!$this -> isModelQuery()) {
			// Query not related to model. We will perform raw insert into target table.
			$id = $DB -> insert($table, $fill);
			$fill -> id = $id;
			return $fill;
		}

		return $this -> class::create($fill)
			-> save();
	}

	/**
	 * Check if any records that match the condition.
	 *
	 * @return bool
	 */
	public function exists() {
		global $DB;

		list($sql, $params) = $this -> makeSQLCall([Expr::value("x")]);

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

		if ($this -> table -> table instanceof Query)
			throw new CodingError("Cannot perform DELETE operation on subquery!");

		$this -> disableAlias = true;

		try {
			list($where, $params) = parent::sequelize($this);
			$result = false;

			if (empty($where))
				return false;

			list($table, $tableParams) = $this -> table -> sequelize($this);

			$sql = "DELETE FROM {$table}"
				. "\nWHERE ({$where})";

			$result = DB::exec($sql, array_merge($tableParams, $params));
		} catch (Exception $e) {
			$this -> disableAlias = false;
			throw $e;
		}

		$this -> disableAlias = false;
		return $result;
	}

	/**
	 * Truncate the table entirely.
	 *
	 * @return bool
	 */
	public function truncate() {
		if ($this -> table -> table instanceof Query)
			throw new CodingError("Cannot perform TRUNCATE operation on subquery!");

		return DB::exec("TRUNCATE TABLE {" . $this -> table -> table -> getName() . "}");
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

		$instances = array();

		list($sql, $params) = $this -> makeSQLCall(limit: false);

		if (empty($sql))
			return array();

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
	 * Return an array of matching records by ids or specified column.
	 *
	 * @param	Column|string	$column
	 * @param	int				$from			Return a subset of records, starting at this point.
	 * @param	int				$count			Return a subset comprising this many records in total.
	 * @return	int[]
	 */
	public function ids(Column|string $column = "#", int $from = 0, int $count = 0) {
		return array_map("intval", $this -> values($column, $from, $count));
	}

	/**
	 * Return an array of values from the specified column.
	 *
	 * @param	Column|string	$column
	 * @param	int				$from			Return a subset of records, starting at this point.
	 * @param	int				$count			Return a subset comprising this many records in total.
	 * @return	string[]
	 */
	public function values(Column|string $column, int $from = 0, int $count = 0) {
		global $DB;

		if ($column === "#") {
			$column = ($this -> isModelQuery())
				? $this -> class::col($column)
				: "id";
		}

		if (is_string($column))
			$column = $this -> getColumn($column);

		$select = new SelectColumn($column, "value");
		list($sql, $params) = $this -> makeSQLCall([$select], limit: false, distinct: true);

		if (empty($sql))
			return array();

		$records = $DB -> execute($sql, $params, $from, $count);
		return array_values(array_map(fn ($record) => $record -> value, $records));
	}

	/**
	 * Return an iterator to iterate over all the found records.
	 * This is to save memory if number of records to be retrieved from DB is high.
	 *
	 * ! **⚠ IMPORTANT:** Remember to call `QueryIterator -> close()` after using it.
	 *
	 * @param	$from				Return a subset of records, starting at this point.
	 * @param	$count				Return a subset comprising this many records in total.
	 * @return	QueryIterator<G>
	 */
	public function iterator(int $from = 0, int $count = 0) {
		global $DB;

		list($sql, $params) = $this -> makeSQLCall(limit: false);

		if (empty($sql))
			return new QueryIterator();

		$iter = $DB -> execute($sql, $params, $from, $count);

		return ($this -> isModelQuery())
			? new QueryIterator($iter, $this -> class)
			: new QueryIterator($iter);
	}

	/**
	 * Count all the matching records and return the amount of it.
	 *
	 * @param	Column|string	$column
	 * @param	bool			$distinct	Select distinct.
	 * @param	bool			$over		Add `OVER()` to select count query.
	 * @return	int
	 */
	public function count(Column|string $column = "x", bool $distinct = false, bool $over = false) {
		global $DB;

		if (is_string($column)) {
			$column = ($column == "x")
				? Expr::value($column)
				: $this -> getColumn($column);
		}

		$select = new SelectColumn(Expr::count($column), "count");
		$select -> over = $over;

		list($sql, $params) = $this -> makeSQLCall([$select], distinct: $distinct);

		if (empty($sql))
			return 0;

		$count = $DB -> execute($sql, $params);
		return empty($count -> count) ? 0 : (int) $count -> count;
	}

	/**
	 * Take the sum of a column for all of the matching records and return the sum of it.
	 *
	 * @param	Column|string	$column
	 * @param	bool			$distinct	Select distinct.
	 * @param	bool			$over		Add `OVER()` to select count query.
	 * @return	int|float
	 */
	public function sum(Column|string $column, bool $distinct = false, bool $over = false) {
		global $DB;

		if (is_string($column))
			$column = $this -> getColumn($column);

		$select = new SelectColumn(Expr::sum($column), "sum");
		$select -> over = $over;

		list($sql, $params) = $this -> makeSQLCall([$select], distinct: $distinct);

		if (empty($sql))
			return 0;

		$count = $DB -> execute($sql, $params);
		return empty($count -> sum) ? 0 : (float) $count -> sum;
	}

	/**
	 * Execute an update statement to update values in database.
	 * This is to be used with `set()`.
	 *
	 * @return	bool
	 */
	public function update() {
		global $DB;

		if ($this -> table -> table instanceof Query)
			throw new CodingError("Cannot perform UPDATE operation on subquery!");

		if (empty($this -> sets))
			throw new CodingError("<code>Query::update()</code> called without any value set directives. Call <code>Query::set()</code> first!");

		$sets = array();
		$params = array();
		$this -> disableAlias = true;
		$result = false;

		try {
			foreach ($this -> sets as $set) {
				[$s, $p] = $set -> sequelize($this);
				$sets[] = $s;
				$params = array_merge($params, $p);
			}

			$sql = "UPDATE {$this -> table -> table -> out()}"
				. "\nSET " . implode(", ", $sets);

			list($where, $wparams) = $this -> sequelize($this);

			if (!empty($where)) {
				$sql .= "\nWHERE {$where}";
				$params = array_merge($params, $wparams);
			}

			$this -> sql($sql, $params);
			$result = $this -> exec();
		} catch (Exception $e) {
			// Make sure we reset flag even when we errored.
			$this -> disableAlias = false;
			throw $e;
		}

		$this -> disableAlias = false;
		return $result;
	}

	/**
	 * Increase value of specified column to the given amount, on all matching rows.
	 *
	 * @param	Column|string		$column
	 * @param	int|float			$amount
	 * @return	bool
	 */
	public function increase(Column|string $column, int|float $amount = 1) {
		global $DB;

		if ($this -> table -> table instanceof Query)
			throw new CodingError("Cannot perform UPDATE operation on subquery!");

		if (is_string($column))
			$column = $this -> getColumn($column);

		[$column, $params] = $column -> sequelize($this);
		$sql = "UPDATE {$this -> table -> table -> out()}"
			. "\nSET {$column} = ({$column} + ({$amount}))";

		list($where, $wparams) = $this -> sequelize($this);

		if (!empty($where)) {
			$sql .= "\nWHERE {$where}";
			$params = array_merge($params, $wparams);
		}

		$this -> sql($sql, $params);
		return $this -> exec();
	}

	/**
	 * Decrease value of specified column to the given amount, on all matching rows.
	 *
	 * @param	string		$column
	 * @param	int|float	$amount
	 * @return	bool
	 */
	public function decrease(string $column, int|float $amount = 1) {
		return $this -> increase($column, -$amount);
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
