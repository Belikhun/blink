<?php

namespace Blink;

/**
 * Search.php
 * 
 * Ultility for searching records in database.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Search {
	public static function whereClause(array $fields, array $params) {
		$where = array();
		$list = array();

		foreach ($params as &$param)
			$param = "%$param%";

		foreach ($fields as $field) {
			$group = array_fill(0, count($params), "LOWER($field) LIKE ?");
			array_push($list, ...$params);
			$where[] = "(" . implode(" AND ", $group) . ")";
		}

		return array(implode(" OR ", $where), $list);
	}

	public static function pageWithQuery(
		string $table,
		string $query = null,
		array $fields = [],
		array $conditions = [],
		string $sort = "",
		int $page = 0,
		int $limit = 20
	) {
		global $DB;
		
		$from = ($page - 1) * $limit;
		$total = 0;

		if (!empty($query)) {
			$params = explode(" ", mb_strtolower($query));
			list($where, $params) = self::whereClause($fields, $params);
		
			$sql = "SELECT * FROM `$table` WHERE $where";

			if (!empty($conditions)) {
				list($cWhere, $cParams) = $DB -> whereClause($conditions);
				$sql .= " AND $cWhere";
				$params = array_merge($params, $cParams);
			}

			if (!empty($sort))
				$sql .= " ORDER BY $sort";

			$total = $DB -> execute("SELECT COUNT('x') FROM `$table` WHERE $where", $params)[0];
			$total = (int) $total -> {"COUNT('x')"};
		
			$records = $DB -> execute($sql, $params, from: $from, limit: $limit);
		} else {
			$total = $DB -> count($table, $conditions);
			$records = $DB -> records($table, $conditions, $sort, from: $from, limit: $limit);
		}
		
		return array($records, new PageInfo($from, $limit, $total));
	}
}