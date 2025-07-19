<?php

namespace Blink\Query\Abstract;

use Blink\Exception\CodingError;
use Blink\Query;
use Blink\Query\Condition;
use Blink\Query\Expression\SubQuery;
use Blink\Query\Expression\Expr;
use Blink\Query\Expression\SequelizableList;
use Blink\Query\QueryBuilder;

/**
 * Query condition builder base class.
 *
 * @template	B
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
abstract class QueryConditionBuilder {

	/**
	 * Indicate that the output of this query is flipped.
	 *
	 * @var bool
	 */
	public bool $flip = false;

	/**
	 * Indicate that the output of this query should be treated with OR instead of AND.
	 *
	 * @var bool
	 */
	public bool $or = false;

	/**
	 * Conditions in this builder.
	 *
	 * @var	array<Condition|QueryBuilder<B>>
	 */
	public array $conditions = array();

	/**
	 * Process args passed and make new condition or query builder from it.
	 *
	 * @param	array	$values		Values from function arguments.
	 * @param	bool	$flip		Flip the output of this condition.
	 * @param	bool	$or			Use OR instead of AND when building this query.
	 * @param	bool	$raw		Pass key and value into query AS-IS.
	 * @return	B
	 */
	public function whereWith(array $values, bool $flip = false, bool $or = false, bool $raw = false) {
		if ($or && empty($this -> conditions))
			$or = false;

		if (empty($values))
			return $this;

		if ($values[0] instanceof \Closure) {
			$child = new QueryBuilder();
			$child -> flip = $flip;
			$child -> or = $or;
			$values[0]($child);

			if (!empty($child -> conditions))
				$this -> conditions[] = $child;

			return $this;
		}

		if (count($values) === 1 && is_array($values[0])) {
			foreach ($values[0] as $column => $value) {
				$left = Expr::processValue($column, resolveAsColumn: true);
				$right = Expr::processValue($value);

				$condititon = new Condition($left, "=", $right);
				$this -> conditions[] = $condititon;
			}

			return $this;
		}

		if (count($values) === 3) {
			if (!$raw) {
				if ($values[2] instanceof Query)
					$values[2] = new SubQuery($values[2]);

				$left = Expr::processValue($values[0], resolveAsColumn: true);
				$right = Expr::processValue($values[2]);
			} else {
				// LOL 😂
				$left = Expr::value($values[0], true);
				$right = Expr::value($values[2], true);
			}

			$comparator = $values[1];

			// Value is a subquery or a list of values.
			if ($right instanceof SequelizableList) {
				// Force operator to be IN or NOT IN.
				$comparator = ($comparator === "<>" || strcasecmp($comparator, "NOT IN") === 0)
					? "NOT IN"
					: "IN";
			}

			$condititon = new Condition($left, $comparator, $right);
			$condititon -> flip = $flip;
			$condititon -> or = $or;

			$this -> conditions[] = $condititon;
			return $this;
		}

		if (count($values) === 2) {
			if (!$raw) {
				if ($values[1] instanceof Query)
					$values[1] = new SubQuery($values[1]);

				$left = Expr::processValue($values[0], resolveAsColumn: true);
				$right = Expr::processValue($values[1]);
			} else {
				// LOL 😂
				$left = Expr::value($values[0], true);
				$right = Expr::value($values[1], true);
			}

			$condititon = ($right instanceof SequelizableList)
				? new Condition($left, "IN", $right)
				: new Condition($left, "=", $right);

			$condititon -> flip = $flip;
			$condititon -> or = $or;

			$this -> conditions[] = $condititon;
			return $this;
		}

		throw new CodingError(static::class . " -> where(): invalid amount of arguments passed to this function");
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
	 * Create and return a new condition group.
	 *
	 * @param	bool				$flip
	 * @param	bool				$or
	 * @return	QueryBuilder<B>
	 */
	public function builder(bool $flip = false, bool $or = false): QueryBuilder {
		$group = new QueryBuilder();
		$group -> flip = $flip;
		$group -> or = $or;
		$this -> conditions[] = $group;

		return $group;
	}

	/**
	 * Build query for this query builder.
	 *
	 * @return array An array contain `[$query, $params]`.
	 */
	public function sequelize(Query $query) {
		$sql = "";
		$params = array();

		foreach ($this -> conditions as $condition) {
			if ($condition instanceof QueryBuilder && empty($condition -> conditions))
				continue;

			[$s, $p] = $condition -> sequelize($query);

			$sql .= !empty($sql)
				? ($condition -> or ? " OR ({$s})" : " AND ({$s})")
				: "({$s})";

			$params = array_merge($params, $p);
		}

		if (!empty($sql)) {
			if ($this -> flip)
				$sql = "NOT ({$sql})";

			$sql = "({$sql})";
		}

		return array($sql, $params);
	}
}
