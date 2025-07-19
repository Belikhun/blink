<?php

namespace Blink\Query\Expression;

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
		$this -> id = "[c:{$table -> table}.{$column}]";
		$this -> table = $table;
		$this -> column = $column;
		$this -> validate();
	}

	public function getID(): string {
		return $this -> id;
	}

	public function validate(bool $throw = true) {
		global $DB;

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
	 * @return	Column|PartialColumn
	 */
	public static function instance(Table|string $table, string $column, bool $fallbackPartial = false): Column|PartialColumn {
		$id = ($table instanceof Table)
			? "[c:{$table -> table}.{$column}]"
			: "[c:{$table}.{$column}]";

		if (isset(static::$instances[$id]))
			return static::$instances[$id];

		try {
			if (is_string($table))
				$table = Table::instance($table);
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
}
