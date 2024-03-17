<?php

namespace Blink\Query;

use Blink\Exception\CodingError;
use Blink\Query;

/**
 * Condition.php
 * 
 * Represent a condition in the query.
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
final class Condition {
	const VALID_OPS = Array( "=", ">", "<", ">=", "<=", "<>", "LIKE", "IN" );

	/**
	 * Indicate that the output of this query is flipped.
	 *
	 * @var bool
	 */
	public bool $flip = false;

	/**
	 * Indicate that this condition should be treated with OR instead of AND when building query.
	 *
	 * @var bool
	 */
	public bool $or = false;

	/**
	 * Raw mode. Key and value will be passed into query AS-IS.
	 * Potential of **SQL INJECTION** attack!
	 *
	 * @var bool
	 */
	public bool $raw = false;

	/**
	 * Condition key value.
	 *
	 * @var string
	 */
	public String $key;

	/**
	 * Condition value.
	 *
	 * @var mixed|Query
	 */
	public mixed $value;

	/**
	 * Valid SQL compare operator. `(=, >, <, >=, <=, <>, LIKE)`
	 *
	 * @var string
	 */
	public String $operator = "=";

	public function __construct(String $key, String $operator, $value) {
		if (!in_array(strtoupper($operator), static::VALID_OPS))
			throw new CodingError(static::class . "(): [{$operator}] is not a valid SQL operator!");

		$this -> key = $key;
		$this -> operator = $operator;
		$this -> value = $value;

		if ($this -> value instanceof Query)
			$this -> operator = "IN";
	}

	/**
	 * Check if the field value is a valid table column.
	 *
	 * @param	string			$field
	 * @return	string|array
	 */
	public static function validateColumnValue(String $field) {
		global $DB;

		if (!str_contains($field, "."))
			return false;

		//! CRITICAL: Validate table and column value. Currently we don't have any method of
		//! storing current tables structure so it is currently impossible for now.
		//! Need to address this in the future to prevent SQL INJECTION!

		list($table, $column) = explode(".", $field);
		return Array( $table, $column );
	}

	/**
	 * Build query for this condition.
	 *
	 * @return array An array contain `[ $query, $params ]`.
	 */
	public function build() {
		$query = "";
		$params = Array();
		$inOp = false;

		if (!$this -> raw) {
			$kVal = $this -> validateColumnValue($this -> key);
			$key = $this -> key;

			if ($kVal) {
				list($kTable, $kColumn) = $kVal;
				$key = "{$kTable}.{$kColumn}";
			}

			if (is_array($this -> value)) {
				// Empty array, this condition will always return
				// false.
				if (empty($this -> value)) {
					return Array(
						$this -> flip ? "TRUE" : "FALSE",
						[]
					);
				}

				$values = implode(", ", array_fill(0, count($this -> value), "?"));

				$query = ($this -> flip)
					? "{$key} NOT IN ({$values})"
					: "{$key} IN ({$values})";

				$inOp = true;
				$params = array_values($this -> value);
			} else if ($this -> value instanceof Query) {
				list($sSql, $sParams) = $this -> value -> makeSQLCall();

				$query = ($this -> flip)
					? "{$key} NOT IN ({$sSql})"
					: "{$key} IN ({$sSql})";

				$params = $sParams;
				$inOp = true;
			} else if ($this -> value === null) {
				$query = "{$key} IS NULL";
			} else {
				$vVal = static::validateColumnValue($this -> value);

				if ($vVal) {
					// Treat as table column.
					list($table, $column) = $vVal;
					$query = "{$key} {$this -> operator} {$table}.{$column}";
				} else {
					// Treat as normal value.
					$query = "{$key} {$this -> operator} ?";
					$params[] = $this -> value;
				}
			}
		} else {
			$query = implode(" ", Array(
				$this -> key,
				$this -> operator,
				$this -> value
			));
		}

		if ($this -> flip && !$inOp)
			$query = "NOT ({$query})";

		return Array( $query, $params );
	}
}
