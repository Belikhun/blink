<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\DB\Exception\DatabaseInvalidTable;
use Blink\DB\Exception\DatabaseTableNotFound;
use Blink\Query\Interface\Sequelizable;

/**
 * Represent a table in select from or join part of query.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class Table implements Sequelizable {

	public readonly string $id;

	public readonly string $table;

	/**
	 * List of available columns defined in database for this table, mapped by column name.
	 *
	 * @var \Blink\DB\ColumnInfo[]
	 */
	public readonly array $columns;

	/**
	 * Contains list of processed table instances.
	 *
	 * @var Table[]
	 */
	protected static array $instances = array();

	protected function __construct(string $table) {
		global $DB;

		$this -> id = "[t:{$table}]";
		$this -> table = $table;

		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this -> table))
			throw new DatabaseInvalidTable($this -> table);

		$this -> columns = $DB -> getColumns($this -> table);

		if (empty($this -> columns))
			throw new DatabaseTableNotFound($this -> table);
	}

	public function getID(): string {
		return $this -> id;
	}

	public function getName() {
		return $this -> table;
	}

	public function out() {
		return "{{$this -> table}}";
	}

	/**
	 * Check if this table have the specified column name
	 *
	 * @param	string	$column		Column name defined in database
	 * @return	bool
	 */
	public function haveColumn(string $column) {
		return !empty($this -> columns[$column]);
	}

	public function sequelize(Query $query): array {
		if ($table = $query -> getAlias($this))
			return ["`{$table}`", []];

		return [$this -> out(), []];
	}

	public function __toString(): string {
		return $this -> out();
	}

	/**
	 * Process table data into a Table instance.
	 *
	 * @param	string		$table		Database table name.
	 * @return	Table
	 */
	public static function instance(string $table): Table {
		$id = "[t:{$table}]";

		if (isset(static::$instances[$id]))
			return static::$instances[$id];

		$instance = new static($table);
		static::$instances[$id] = $instance;
		return $instance;
	}
}
