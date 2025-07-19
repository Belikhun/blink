<?php

namespace Blink\Query;

/**
 * Model query advanced filter. Built for dashboard's filter editor.
 *
 * @extends		parent<static>
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class QueryFilterGroup {

	public const OP_AND = "and";

	public const OP_AND_NOT = "andNot";

	public const OP_OR = "or";

	public QueryFilter $filter;

	public ?QueryFilterGroup $parent = null;

	/** @var (QueryFilterGroup | QueryFilterItem)[] */
	public array $items;

	public string $operator = QueryFilterGroup::OP_AND;

	public function __construct(QueryFilter $filter, QueryFilterGroup $parent = null) {
		$this -> filter = $filter;
		$this -> parent = $parent;
		$this -> items = array();
	}

	public function parse(array $filterData) {
		$this -> operator = $filterData["operator"];

		foreach ($filterData["items"] as $itemData) {
			if ($itemData["@type"] === "group") {
				$group = new static($this -> filter, $this);
				$this -> items[] = $group -> parse($itemData);
				continue;
			}

			$item = new QueryFilterItem($this -> filter, $this);
			$this -> items[] = $item -> parse($itemData);
		}

		return $this;
	}

	public function apply(QueryBuilder $query) {
		if (empty($this -> items))
			return $query;

		$or = false;
		$flip = false;

		switch ($this -> operator) {
			case static::OP_AND_NOT:
				$flip = true;
				break;

			case static::OP_OR:
				$or = true;
				break;
		}

		foreach ($this -> items as $item) {
			if ($item instanceof QueryFilterGroup) {
				$item -> apply($query -> builder($flip, $or));
				continue;
			}

			$item -> apply($query, $flip, $or);
		}

		return $query;
	}

	public function evaluate(array|\stdClass $data, bool $skipQueryColumn = true) {
		if (empty($this -> items))
			return true;

		$data = (object) $data;

		switch ($this -> operator) {
			case static::OP_AND: {
				foreach ($this -> items as $item) {
					$result = $item -> evaluate($data, $skipQueryColumn);

					if ($result === QueryFilter::IGNORE_RESULT)
						continue;

					if (!$result)
						return false;
				}

				return true;
			}

			case static::OP_AND_NOT: {
				foreach ($this -> items as $item) {
					$result = $item -> evaluate($data, $skipQueryColumn);

					if ($result === QueryFilter::IGNORE_RESULT)
						continue;

					if ($result)
						return false;
				}

				return true;
			}

			case static::OP_OR: {
				foreach ($this -> items as $item) {
					$result = $item -> evaluate($data, $skipQueryColumn);

					if ($result === QueryFilter::IGNORE_RESULT)
						continue;

					if ($result)
						return true;
				}

				return false;
			}
		}

		return false;
	}

	public function calculateFingerprint() {
		$dbFp = "";
		$softFp = "";

		foreach ($this -> items as $item) {
			if ($item instanceof QueryFilterGroup) {
				$result = $item -> calculateFingerprint();
				$dbFp .= $result[0];
				$softFp .= $result[1];
				continue;
			}

			if ($item -> column === QueryFilter::SOFT_FILTER)
				$softFp .= $item -> calculateFingerprint();
			else
				$dbFp .= $item -> calculateFingerprint();
		}

		return [$dbFp, $softFp];
	}
}
