<?php

namespace Blink\Query\Expression;

use Blink\DB;
use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Join values together with a separator between them.
 *
 * MySQL has `CONCAT_WS`; SQLite only got one in 3.44, which is newer than
 * plenty of what blink runs on, so there it is spelled out with the `||`
 * operator instead.
 *
 * The two differ on nulls: MySQL drops a null value along with the separator
 * that would have followed it, while the `||` form contributes an empty string
 * and keeps the separator. Nothing blink uses this for cares, and matching
 * MySQL exactly would mean a different statement shape per null.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ConcatWs implements Sequelizable {
	public readonly string $id;

	public readonly Sequelizable $separator;

	/** @var Sequelizable[] */
	public readonly array $values;

	public function __construct(Sequelizable $separator, Sequelizable ...$values) {
		$this -> id = "[cws:" . randString(7) . "]";
		$this -> separator = $separator;
		$this -> values = $values;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		if (empty($this -> values))
			return array("''", array());

		list($separatorSQL, $separatorParams) = $this -> separator -> sequelize($query);
		$parts = array();
		$partParams = array();

		foreach ($this -> values as $value) {
			list($sql, $params) = $value -> sequelize($query);
			$parts[] = $sql;
			$partParams[] = $params;
		}

		// Placeholders bind in the order they appear in the statement, so each
		// branch collects its parameters in the order it writes them.
		if (DB::family() === "mysql") {
			$params = $separatorParams;

			foreach ($partParams as $group)
				$params = array_merge($params, $group);

			return array("CONCAT_WS({$separatorSQL}, " . implode(", ", $parts) . ")", $params);
		}

		$pieces = array();
		$params = array();

		foreach ($parts as $index => $part) {
			if ($index > 0) {
				$pieces[] = $separatorSQL;
				$params = array_merge($params, $separatorParams);
			}

			$pieces[] = "COALESCE({$part}, '')";
			$params = array_merge($params, $partParams[$index]);
		}

		return array("(" . implode(" || ", $pieces) . ")", $params);
	}
}
