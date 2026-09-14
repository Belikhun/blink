<?php

namespace Blink\Terra;

/**
 * An index on one or more columns.
 *
 * Terra always emits these as their own `CREATE INDEX` statement rather than
 * folding them into the table definition, so a name generated here is the name
 * every engine ends up with.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Index {
	/** @var string[] */
	public array $columns;

	public string $name;

	public bool $unique;

	public function __construct(string $table, array $columns, bool $unique = false, string $name = null) {
		$this -> columns = array_values($columns);
		$this -> unique = $unique;
		$this -> name = $name ?? static::generateName($table, $this -> columns, $unique);
	}

	public function name(string $name): static {
		$this -> name = $name;
		return $this;
	}

	/**
	 * `{table}_{columns}_idx`, or `_uix` for a unique one.
	 *
	 * An index name has to be unique across the whole schema in some engines,
	 * so the table name is part of it.
	 */
	public static function generateName(string $table, array $columns, bool $unique): string {
		return implode("_", array_merge(
			array( $table ),
			$columns,
			array( $unique ? "uix" : "idx" )
		));
	}
}
