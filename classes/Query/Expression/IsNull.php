<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Is null expression.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class IsNull implements Sequelizable {

	public readonly string $id;

	public Sequelizable $target;

	public bool $flip = false;

	public function __construct(Sequelizable $target, bool $flip = false) {
		$this -> id = "[isn:" . randString(7) . "]";
		$this -> target = $target;
		$this -> flip = $flip;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		[$tSql, $tParams] = $this -> target -> sequelize($query);
		return [
			(!$this -> flip)
				? "{$tSql} IS NULL"
				: "{$tSql} IS NOT NULL",

			$tParams
		];
	}
}
