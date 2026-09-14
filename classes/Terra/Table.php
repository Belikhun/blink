<?php

namespace Blink\Terra;

/**
 * The shape of a table, as a layer describes it.
 *
 * Collected first and compiled second: a {@see Dialect} reads the finished
 * object and decides what statements the engine in front of it needs.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Table {
	const MODE_CREATE = "create";

	const MODE_ALTER = "alter";

	const TYPE_INTEGER = "integer";
	const TYPE_BIG_INTEGER = "bigInteger";
	const TYPE_TINY_INTEGER = "tinyInteger";
	const TYPE_BOOLEAN = "boolean";
	const TYPE_FLOAT = "float";
	const TYPE_DECIMAL = "decimal";
	const TYPE_STRING = "string";
	const TYPE_TEXT = "text";
	const TYPE_MEDIUM_TEXT = "mediumText";
	const TYPE_LONG_TEXT = "longText";
	const TYPE_JSON = "json";
	const TYPE_TIMESTAMP = "timestamp";
	const TYPE_BINARY = "binary";

	public string $name;

	public string $mode;

	/** @var Column[] */
	public array $columns = array();

	/** @var Column[] */
	public array $changes = array();

	/** @var Index[] */
	public array $indexes = array();

	/** @var ForeignKey[] */
	public array $foreignKeys = array();

	/** @var string[] */
	public array $droppedColumns = array();

	/** @var string[] */
	public array $droppedIndexes = array();

	/** @var array<string, string> */
	public array $renamedColumns = array();

	/**
	 * Columns making up the primary key, when it is not a single
	 * auto-incrementing id.
	 *
	 * @var string[]
	 */
	public array $primary = array();

	public function __construct(string $name, string $mode = self::MODE_CREATE) {
		$this -> name = $name;
		$this -> mode = $mode;
	}

	public function creating(): bool {
		return $this -> mode === static::MODE_CREATE;
	}

	/**
	 * An auto-incrementing primary key.
	 */
	public function id(string $name = "id"): Column {
		return $this -> add($name, static::TYPE_BIG_INTEGER)
			-> unsigned()
			-> primary()
			-> autoIncrement();
	}

	public function integer(string $name): Column {
		return $this -> add($name, static::TYPE_INTEGER);
	}

	public function bigInteger(string $name): Column {
		return $this -> add($name, static::TYPE_BIG_INTEGER);
	}

	public function tinyInteger(string $name): Column {
		return $this -> add($name, static::TYPE_TINY_INTEGER);
	}

	public function boolean(string $name): Column {
		return $this -> add($name, static::TYPE_BOOLEAN);
	}

	public function float(string $name): Column {
		return $this -> add($name, static::TYPE_FLOAT);
	}

	public function decimal(string $name, int $total = 10, int $places = 2): Column {
		return $this -> add($name, static::TYPE_DECIMAL, array( "total" => $total, "places" => $places ));
	}

	public function string(string $name, int $length = 255): Column {
		return $this -> add($name, static::TYPE_STRING, array( "length" => $length ));
	}

	public function text(string $name): Column {
		return $this -> add($name, static::TYPE_TEXT);
	}

	public function mediumText(string $name): Column {
		return $this -> add($name, static::TYPE_MEDIUM_TEXT);
	}

	public function longText(string $name): Column {
		return $this -> add($name, static::TYPE_LONG_TEXT);
	}

	public function json(string $name): Column {
		return $this -> add($name, static::TYPE_JSON);
	}

	/**
	 * A point in time, stored as an epoch integer.
	 *
	 * Blink has always passed timestamps around as integers, and keeping the
	 * column an integer means no engine is quietly converting them into its
	 * own idea of a date on the way in or out.
	 */
	public function timestamp(string $name): Column {
		return $this -> add($name, static::TYPE_TIMESTAMP);
	}

	/**
	 * The `created` and `updated` pair every blink model expects.
	 */
	public function timestamps(): void {
		$this -> timestamp("created") -> default(0);
		$this -> timestamp("updated") -> default(0);
	}

	public function binary(string $name): Column {
		return $this -> add($name, static::TYPE_BINARY);
	}

	/**
	 * Redefine an existing column. Rejected on SQLite, which cannot alter a
	 * column in place.
	 */
	public function change(string $name, string $type, array $options = array()): Column {
		$column = new Column($this, $name, $type, $options);
		$this -> changes[] = $column;
		return $column;
	}

	/**
	 * @param	string|string[]		$columns
	 */
	public function index(string|array $columns, string $name = null): Index {
		$index = new Index($this -> name, (array) $columns, false, $name);
		$this -> indexes[] = $index;
		return $index;
	}

	/**
	 * @param	string|string[]		$columns
	 */
	public function unique(string|array $columns, string $name = null): Index {
		$index = new Index($this -> name, (array) $columns, true, $name);
		$this -> indexes[] = $index;
		return $index;
	}

	/**
	 * @param	string|string[]		$columns
	 */
	public function foreign(string|array $columns, string $name = null): ForeignKey {
		$key = new ForeignKey($this -> name, (array) $columns, $name);
		$this -> foreignKeys[] = $key;
		return $key;
	}

	/**
	 * @param	string|string[]		$columns
	 */
	public function primary(string|array $columns): void {
		$this -> primary = (array) $columns;
	}

	public function dropColumn(string ...$names): void {
		foreach ($names as $name)
			$this -> droppedColumns[] = $name;
	}

	public function dropIndex(string $name): void {
		$this -> droppedIndexes[] = $name;
	}

	public function renameColumn(string $from, string $to): void {
		$this -> renamedColumns[$from] = $to;
	}

	protected function add(string $name, string $type, array $options = array()): Column {
		$column = new Column($this, $name, $type, $options);
		$this -> columns[] = $column;
		return $column;
	}
}
