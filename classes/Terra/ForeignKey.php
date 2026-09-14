<?php

namespace Blink\Terra;

/**
 * A foreign key constraint.
 *
 * SQLite can only carry one inside a `CREATE TABLE`, so Terra rejects adding
 * a constraint to a table that already exists rather than pretending it
 * worked.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ForeignKey {
	/** @var string[] */
	public array $columns;

	public string $name;

	public string $table = "";

	/** @var string[] */
	public array $references = array();

	public ?string $onDelete = null;

	public ?string $onUpdate = null;

	public function __construct(string $table, array $columns, string $name = null) {
		$this -> columns = array_values($columns);
		$this -> name = $name ?? implode("_", array_merge(array( $table ), $this -> columns, array( "fk" )));
	}

	/**
	 * @param	string			$table
	 * @param	string|string[]	$columns
	 */
	public function references(string $table, string|array $columns = "id"): static {
		$this -> table = $table;
		$this -> references = (array) $columns;
		return $this;
	}

	public function onDelete(string $action): static {
		$this -> onDelete = strtoupper($action);
		return $this;
	}

	public function onUpdate(string $action): static {
		$this -> onUpdate = strtoupper($action);
		return $this;
	}

	public function cascadeOnDelete(): static {
		return $this -> onDelete("CASCADE");
	}

	public function nullOnDelete(): static {
		return $this -> onDelete("SET NULL");
	}
}
