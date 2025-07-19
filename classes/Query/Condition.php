<?php

namespace Blink\Query;

use Blink\Query;
use Blink\Query\Expression\Comparator;
use Blink\Query\Interface\Sequelizable;

/**
 * Represent a single condition in query.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
final class Condition implements Sequelizable {

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

	public Comparator $comparator;

	public function __construct(Sequelizable $left, string $comparator, Sequelizable $right) {
		$this -> comparator = new Comparator($left, $comparator, $right);
	}

	public function getID(): string {
		return $this -> comparator -> getID();
	}

	public function sequelize(Query $query): array {
		[$sql, $params] = $this -> comparator -> sequelize($query);

		if ($this -> flip)
			$sql = "NOT ({$sql})";

		return [$sql, $params];
	}
}
