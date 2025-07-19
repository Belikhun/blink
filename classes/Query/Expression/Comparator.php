<?php

namespace Blink\Query\Expression;

use Blink\Exception\CodingError;
use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Value comparator.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class Comparator implements Sequelizable {

	protected const VALID_OPS = array( "=", ">", "<", ">=", "<=", "<>", "LIKE", "IN" );

	public readonly string $id;

	public Sequelizable $left;

	public string $comparator = "=";

	public Sequelizable $right;

	public function __construct(Sequelizable $left, string $comparator, Sequelizable $right) {
		if (!in_array(strtoupper($comparator), static::VALID_OPS))
			throw new CodingError("<code>{$comparator}</code> is not a valid SQL comparator!");

		$this -> id = "[cmp:" . randString(7) . "]";
		$this -> left = $left;
		$this -> comparator = $comparator;
		$this -> right = $right;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		[$lSql, $lParams] = $this -> left -> sequelize($query);
		$comparator = $this -> comparator;

		// Special condition for null value
		if ($this -> right instanceof NativeValue) {
			if ($this -> right -> value === null) {
				if ($comparator == "<>")
					return ["{$lSql} IS NOT NULL", $lParams];

				return ["{$lSql} IS NULL", $lParams];
			}

			if (is_bool($this -> right -> value))
				$comparator = "IS";
		}

		[$rSql, $rParams] = $this -> right -> sequelize($query);

		if ($this -> right instanceof SequelizableList) {
			if (empty($this -> right -> values))
				return ["FALSE", []];

			$comparator = "IN";
		}

		return ["{$lSql} {$comparator} {$rSql}", array_merge($lParams, $rParams)];
	}
}
