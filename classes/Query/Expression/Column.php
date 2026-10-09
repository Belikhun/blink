<?php

namespace Blink\Query\Expression;

use Blink\DB\Database;
use Blink\DB\Exception\DatabaseColumnNotFound;
use Blink\DB\Exception\DatabaseInvalidColumn;
use Exception;
use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use Blink\Query\Expression\Table;

/**
 * Represent a column in expression.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class Column implements Sequelizable {

	public readonly string $id;

	public Table $table;

	public readonly string $column;

	/**
	 * Contains list of processed columns
	 *
	 * @var Column[]
	 */
	protected static array $instances = array();

	protected function __construct(Table $table, string $column) {
		$this -> id = "[c:{$table -> database -> name}:{$table -> table}.{$column}]";
		$this -> table = $table;
		$this -> column = $column;
		$this -> validate();
	}

	public function getID(): string {
		return $this -> id;
	}

	public function validate(bool $throw = true) {
		if ($this -> column == "*")
			return true;

		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this -> column)) {
			if ($throw)
				throw new DatabaseInvalidColumn($this -> column);

			return false;
		}

		// Invalid table or column.
		if (!$this -> table -> haveColumn($this -> column)) {
			if ($throw)
				throw new DatabaseColumnNotFound($this -> column, $this -> table -> getName());

			return false;
		}

		return true;
	}

	public function getName() {
		return $this -> table -> getName() . ".{$this -> column}";
	}

	public function out(): string {
		return "{$this -> table}.{$this -> column}";
	}

	public function sequelize(Query $query): array {
		// Note: We shouldn't resolve to column alias.
		// https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html
		// if ($column = $query -> getAlias($this -> id))
		// 	return ["`{$column}`", []];

		[$t, $p] = $this -> table -> sequelize($query);
		return ["{$t}.{$this -> column}", $p];
	}

	public function __toString(): string {
		return $this -> out();
	}

	/**
	 * Process column data into a Column instance
	 *
	 * @param	Table|string			$table				Table
	 * @param	string					$column				Column name
	 * @param	bool					$fallbackPartial	Fallback as a partial column if the table is not found
	 * @param	?Database				$database			The instance a table name is looked up on. A Table
	 * 														object carries its own, and this is ignored for it.
	 * @return	Column|PartialColumn
	 */
	public static function instance(Table|string $table, string $column, bool $fallbackPartial = false, ?Database $database = null): Column|PartialColumn {
		$database = ($table instanceof Table)
			? $table -> database
			: ($database ?? Database::default());

		$tableName = ($table instanceof Table) ? $table -> table : $table;
		$id = "[c:{$database -> name}:{$tableName}.{$column}]";

		if (isset(static::$instances[$id]))
			return static::$instances[$id];

		try {
			if (is_string($table))
				$table = Table::instance($table, $database);
		} catch (Exception $e) {
			if (!$fallbackPartial)
				throw $e;

			$instance = new PartialColumn($column);
			$instance -> table = ($table instanceof Table)
				? $table -> table
				: $table;

			return $instance;
		}

		$instance = new static($table, $column);
		static::$instances[$id] = $instance;

		return $instance;
	}

	/**
	 * Drop cached column instances. See {@see Table::forget()}.
	 *
	 * @param	?string[]				$tables		Only columns of these tables, or every column when omitted.
	 * @param	Database|string|null	$database	Only this instance, or every instance when omitted.
	 */
	public static function forget(?array $tables = null, Database|string|null $database = null): void {
		if ($tables === null && $database === null) {
			static::$instances = array();
			return;
		}

		$name = ($database instanceof Database) ? $database -> name : $database;

		foreach (static::$instances as $key => $instance) {
			if ($name !== null && $instance -> table -> database -> name !== $name)
				continue;

			if ($tables !== null && !in_array($instance -> table -> table, $tables, true))
				continue;

			unset(static::$instances[$key]);
		}
	}
}
