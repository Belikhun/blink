<?php

namespace Blink\Query\Expression;

use Blink\DB\Database;
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
	 * The instance this table lives on.
	 *
	 * @var Database
	 */
	public readonly Database $database;

	/**
	 * List of available columns defined in database for this table, mapped by column name.
	 *
	 * @var \Blink\DB\ColumnInfo[]
	 */
	public readonly array $columns;

	/**
	 * Contains list of processed table instances, keyed by instance and
	 * table name.
	 *
	 * @var Table[]
	 */
	protected static array $instances = array();

	protected function __construct(string $table, Database $database) {
		$this -> id = "[t:{$database -> name}:{$table}]";
		$this -> table = $table;
		$this -> database = $database;

		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this -> table))
			throw new DatabaseInvalidTable($this -> table);

		$this -> columns = $database -> getColumns($this -> table);

		if (empty($this -> columns))
			throw new DatabaseTableNotFound($this -> table, "Database instance: {$database -> name}");
	}

	public function getID(): string {
		return $this -> id;
	}

	public function getName() {
		return $this -> table;
	}

	public function out() {
		return $this -> table;
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
	 * @param	?Database	$database	The instance the table lives on, the default when omitted.
	 * @return	Table
	 */
	public static function instance(string $table, ?Database $database = null): Table {
		$database = $database ?? Database::default();
		$id = "[t:{$database -> name}:{$table}]";

		if (isset(static::$instances[$id]))
			return static::$instances[$id];

		$instance = new static($table, $database);
		static::$instances[$id] = $instance;
		return $instance;
	}

	/**
	 * Drop cached table instances, so the next lookup re-reads the schema.
	 *
	 * Terra calls this after a layer changes a table: the cached instance
	 * still lists the columns the table had before.
	 *
	 * @param	?string[]			$tables		Only these tables, or every table when omitted.
	 * @param	Database|string|null	$database	Only this instance, or every instance when omitted.
	 */
	public static function forget(?array $tables = null, Database|string|null $database = null): void {
		if ($tables === null && $database === null) {
			static::$instances = array();
			return;
		}

		$name = ($database instanceof Database) ? $database -> name : $database;

		foreach (static::$instances as $key => $instance) {
			if ($name !== null && $instance -> database -> name !== $name)
				continue;

			if ($tables !== null && !in_array($instance -> table, $tables, true))
				continue;

			unset(static::$instances[$key]);
		}
	}
}
