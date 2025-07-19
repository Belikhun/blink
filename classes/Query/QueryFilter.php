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
class QueryFilter {

	public const SOFT_FILTER = "<softFiltered>";

	public const IGNORE_RESULT = -1;

	public array $data;

	public array $columns;

	public QueryFilterGroup $mainGroup;

	public function __construct(array $columns, array $filterData) {
		$this -> data = $filterData;
		$this -> columns = $columns;
		$this -> mainGroup = new QueryFilterGroup($this);
		$this -> mainGroup -> parse($filterData);
	}

	public function apply(QueryBuilder $query) {
		if (empty($this -> mainGroup -> items))
			return $query;

		return $this -> mainGroup -> apply($query);
	}

	/**
	 * Filter an array based on current filter, programmatically.
	 *
	 * @template	DT
	 * @param		DT[]	$data
	 * @param		bool	$skipQueryColumn		Skip filtering for columns that is filtered in database table.
	 * @return		DT[]
	 */
	public function filter(array $data, bool $skipQueryColumn = true) {
		$filtered = array();

		foreach ($data as $item) {
			if ($this -> mainGroup -> evaluate($item, $skipQueryColumn))
				$filtered[] = $item;
		}

		return $filtered;
	}

	/**
	 * Calculate and return the filter's fingerprint for both DB mode
	 * and soft mode.
	 *
	 * @return	string[]	Two different fingerprint based on their type (`[$dbFp, $softFp]`)
	 */
	public function calculateFingerprint() {
		$result = $this -> mainGroup -> calculateFingerprint();
		return [md5($result[0]), md5($result[1])];
	}
}
