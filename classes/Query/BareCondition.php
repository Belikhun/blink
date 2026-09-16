<?php

namespace Blink\Query;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;

/**
 * A condition that is already complete on its own.
 *
 * Most conditions are a comparison and are built by {@see Condition}. Some
 * expressions are a whole condition by themselves: `Expr::isNull()` and its
 * siblings compile to `x IS NULL` with nothing on either side to compare.
 * This carries one of those through the condition list, which needs the
 * `flip` and `or` flags every entry in it has.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
final class BareCondition implements Sequelizable {
	/**
	 * Indicate that the output of this condition is flipped.
	 *
	 * @var bool
	 */
	public bool $flip = false;

	/**
	 * Indicate that this condition should be treated with OR instead of AND
	 * when building the query.
	 *
	 * @var bool
	 */
	public bool $or = false;

	public function __construct(public Sequelizable $expression) {}

	public function getID(): string {
		return $this -> expression -> getID();
	}

	public function sequelize(Query $query): array {
		[$sql, $params] = $this -> expression -> sequelize($query);

		if ($this -> flip)
			$sql = "NOT ({$sql})";

		return array($sql, $params);
	}
}
