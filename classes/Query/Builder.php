<?php

namespace Blink\Query;

use Blink\Exception\CodingError;

/**
 * Builder.php
 * 
 * Contain the building block of a query.
 * 
 * @template	B
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Builder {
	/**
	 * Indicate that the output of this query is flipped.
	 * @var bool
	 */
	public bool $flip = false;

	/**
	 * Indicate that the output of this query should be treated with OR instead of AND.
	 * @var bool
	 */
	public bool $or = false;

	/**
	 * Conditions in this builder.
	 * @var	array<Condition|Builder>
	 */
	public Array $conditions = Array();

	/**
	 * Process args passed and make new condition or query builder from it.
	 *
	 * @param	array	$values		Values from function arguments.
	 * @param	bool	$flip		Flip the output of this condition.
	 * @param	bool	$or			Use OR instead of AND when building this query.
	 * @param	bool	$raw		Pass key and value into query AS-IS.
	 * @return	B
	 */
	protected function whereWith(Array $values, bool $flip = false, bool $or = false, bool $raw = false) {
		if ($or && empty($this -> conditions))
			$or = false;

		if (empty($values))
			return $this;

		if ($values[0] instanceof \Closure) {
			$child = new self();
			$child -> flip = $flip;
			$child -> or = $or;
			$child -> raw = $raw;
			$values[0]($child);

			if (!empty($child -> conditions))
				$this -> conditions[] = $child;

			return $this;
		} else if (is_array($values[0])) {
			foreach ($values[0] as $key => $value) {
				$condititon = new Condition($key, "=", $value);
				$condititon -> flip = $flip;
				$condititon -> or = $or;
				$condititon -> raw = $raw;

				$this -> conditions[] = $condititon;
			}

			return $this;
		} else if (count($values) === 3) {
			$condititon = new Condition($values[0], $values[1], $values[2]);
			$condititon -> flip = $flip;
			$condititon -> or = $or;
			$condititon -> raw = $raw;

			$this -> conditions[] = $condititon;
			return $this;
		} else if (count($values) === 2) {
			$condititon = new Condition($values[0], "=", $values[1]);
			$condititon -> flip = $flip;
			$condititon -> or = $or;
			$condititon -> raw = $raw;

			$this -> conditions[] = $condititon;
			return $this;
		}

		throw new CodingError(static::class . " -> where(): invalid arguments passed to this function.");
	}

	/**
	 * Add select condtition to this query.
	 * Example calls:
	 *
	 * * `-> where("key", $value)`
	 * * `-> where("key", [1, 2, 3, "value"])`
	 * * `-> where("key", "<", $value)`
	 * * `-> where(function(Builder $query) {})`
	 *
	 * @return	B
	 */
	public function where(...$args) {
		return $this -> whereWith($args);
	}

	/**
	 * Add select condtition to this query.
	 *
	 * This will create a condition in raw mode, which will pass key and value directly into
	 * the fully built query AS-IS. This can cause **SQL INJECTION** if not used carefully.
	 *
	 * Example calls:
	 *
	 * * `-> whereRaw("key", $value)`
	 * * `-> whereRaw("key", [1, 2, 3, "value"])`
	 * * `-> whereRaw("key", "<", $value)`
	 * * `-> whereRaw(function(Builder $query) {})`
	 *
	 * @return	B
	 */
	public function whereRaw(...$args) {
		return $this -> whereWith($args, raw: true);
	}

	/**
	 * Add select condtition to this query, which the output flipped.
	 * Example calls:
	 *
	 * * `-> whereNot("key", $value)`
	 * * `-> whereNot("key", [1, 2, 3, "value"])`
	 * * `-> whereNot("key", "<", $value)`
	 * * `-> whereNot(function(Builder $query) {})`
	 *
	 * @return	B
	 */
	public function whereNot(...$args) {
		return $this -> whereWith($args, flip: true);
	}

	/**
	 * Add select condtition to this query, using OR.
	 * Example calls:
	 *
	 * * `-> whereOr("key", $value)`
	 * * `-> whereOr("key", [1, 2, 3, "value"])`
	 * * `-> whereOr("key", "<", $value)`
	 * * `-> whereOr(function(Builder $query) {})`
	 *
	 * @return	B
	 */
	public function whereOr(...$args) {
		return $this -> whereWith($args, or: true);
	}

	/**
	 * Add select condtition to this query, using OR, and output flipped.
	 * Example calls:
	 *
	 * * `-> whereOrNot("key", $value)`
	 * * `-> whereOrNot("key", [1, 2, 3, "value"])`
	 * * `-> whereOrNot("key", "<", $value)`
	 * * `-> whereOrNot(function(Builder $query) {})`
	 *
	 * @return	B
	 */
	public function whereOrNot(...$args) {
		return $this -> whereWith($args, flip: true, or: true);
	}

	/**
	 * Build query for this query builder.
	 *
	 * @return array An array contain `[ $query, $params ]`.
	 */
	public function build() {
		$query = "";
		$params = Array();

		foreach ($this -> conditions as $condition) {
			list( $q, $p ) = $condition -> build();

			$query .= !empty($query)
				? ($condition -> or ? " OR ({$q})" : " AND ({$q})")
				: $q;

			$params = array_merge($params, $p);
		}

		if ($this -> flip)
			$query = "NOT ({$query})";

		return Array( $query, $params );
	}
}