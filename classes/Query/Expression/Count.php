<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * A `COUNT()` over a column or expression.
 *
 * Its own class rather than a {@see CallableFunction} because `DISTINCT` sits
 * inside the parentheses, where an argument cannot reach.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Count implements Sequelizable {
	public readonly string $id;

	public readonly Sequelizable $target;

	public bool $distinct = false;

	/**
	 * @param	Sequelizable|string		$target		Column or expression to count.
	 */
	public function __construct(Sequelizable|string $target, bool $distinct = false) {
		$this -> id = "[cnt:" . randString(7) . "]";
		$this -> target = Expr::processValue($target, resolveAsColumn: true);
		$this -> distinct = $distinct;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		list($target, $params) = $this -> target -> sequelize($query);

		return array(
			$this -> distinct
				? "COUNT(DISTINCT {$target})"
				: "COUNT({$target})",
			$params
		);
	}
}
