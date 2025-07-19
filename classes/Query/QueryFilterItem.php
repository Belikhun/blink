<?php

namespace Blink\Query;

use Blink\Exception\CodingError;
use Blink\Model;
use Blink\Query\Expression\Expr;
use Blink\Query\Interface\Sequelizable;

/**
 * Model query advanced filter. Built for dashboard's filter editor.
 *
 * @extends		parent<static>
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class QueryFilterItem {

	public string $name;

	public Sequelizable|string $column;

	public string $comparator;

	public $value = null;

	public QueryFilter $filter;

	public QueryFilterGroup $parent;

	public function __construct(QueryFilter $filter, QueryFilterGroup $parent) {
		$this -> filter = $filter;
		$this -> parent = $parent;
	}

	public function parse(array $filterData) {
		$this -> name = $filterData["name"];

		if (empty($this -> filter -> columns[$this -> name]))
			throw new CodingError("Undefined filter column <code>{$this -> name}</code> for query filter instance!");

		$this -> column = $this -> filter -> columns[$this -> name];
		$this -> comparator = $filterData["comparator"];
		$this -> value = $filterData["value"];

		if (is_string($this -> column) && $this -> column != QueryFilter::SOFT_FILTER)
			$this -> column = Expr::processValue($this -> column, resolveAsColumn: true);

		return $this;
	}

	public function apply(QueryBuilder $query, bool $flip = false, bool $or = false) {
		if ($this -> column === QueryFilter::SOFT_FILTER)
			return $query;

		$condition = null;
		$value = null;

		switch ($this -> comparator) {
			case "isNull":
				$condition = "=";
				$value = null;
				break;

			case "isNotNull":
				$condition = "<>";
				$value = null;
				break;

			case "isEmpty":
				$condition = "IN";
				$value = [null, ""];
				break;

			case "equal":
				$condition = "=";
				$value = $this -> value;
				break;

			case "notEqual":
				$condition = "<>";
				$value = $this -> value;
				break;

			case "less":
				$condition = "<";
				$value = $this -> value;
				break;

			case "lessEq":
			case "beforeDate":
				$condition = "<=";
				$value = $this -> value;
				break;

			case "more":
				$condition = ">";
				$value = $this -> value;
				break;

			case "moreEq":
			case "afterDate":
				$condition = ">=";
				$value = $this -> value;
				break;

			case "contain":
			case "startWith":
			case "endWith": {
				$value = "%{$this -> value}%";

				if ($this -> comparator === "startWith")
					$value = "{$this -> value}%";
				else if ($this -> comparator === "endWith")
					$value = "%{$this -> value}";

				$query -> where(Expr::processValue($this -> column, resolveAsColumn: true), "LIKE", strtolower($value));
				return $query;
			}

			case "isTrue":
				$condition = "=";
				$value = true;
				break;

			case "isFalse":
				$condition = "=";
				$value = false;
				break;
		}

		$query -> whereWith([$this -> column, $condition, $value], $flip, $or);
		return $query;
	}

	public function evaluate(array|\stdClass $data, bool $skipQueryColumn = true): bool|int {
		// We don't need to do a filter again if we have filtered
		// the data in database.
		if ($skipQueryColumn && $this -> column !== QueryFilter::SOFT_FILTER)
			return QueryFilter::IGNORE_RESULT;

		$data = (object) $data;
		$value = $data -> {$this -> name};

		if (is_array($value)) {
			// Specical case when doing isEmpty operator on an empty array.
			if ($this -> comparator == "isEmpty" && empty($value))
				return true;

			foreach ($value as $item) {
				if ($this -> evaluateValue($item, $this -> comparator, $this -> value))
					return true;
			}

			return false;
		}

		return $this -> evaluateValue($value, $this -> comparator, $this -> value);
	}

	protected function evaluateValue($testValue, string $comparator, $filterValue = null): bool {
		switch ($comparator) {
			case "isNull":
				return ($testValue == null);

			case "isNotNull":
				return ($testValue != null);

			case "isEmpty":
				return empty($testValue);

			case "equal": {
				if ($testValue instanceof Model)
					return $testValue -> getPrimaryValue() == $filterValue;

				return $testValue == $filterValue;
			}

			case "notEqual": {
				if ($testValue instanceof Model)
					return $testValue -> getPrimaryValue() != $filterValue;

				return $testValue != $filterValue;
			}

			case "less":
				return $testValue < $filterValue;

			case "lessEq":
			case "beforeDate":
				return $testValue <= $filterValue;

			case "more":
				return $testValue > $filterValue;

			case "moreEq":
			case "afterDate":
				return $testValue >= $filterValue;

			case "contain": {
				if (empty($testValue))
					return false;

				return str_contains(mb_strtolower($testValue), mb_strtolower($filterValue));
			}

			case "startWith": {
				if (empty($testValue))
					return false;

				return str_starts_with(mb_strtolower($testValue), mb_strtolower($filterValue));
			}

			case "endWith": {
				if (empty($testValue))
					return false;

				return str_ends_with(mb_strtolower($testValue), mb_strtolower($filterValue));
			}

			case "isTrue":
				return (!!$testValue);

			case "isFalse":
				return (!$testValue);
		}

		return false;
	}

	public function calculateFingerprint(): string {
		return "{$this -> comparator}:" . ($this -> value ?? "nul");
	}
}
