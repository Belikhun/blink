<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Arithmetic operator.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class ArithmeticOperator implements Sequelizable {

	/**
	 * Addition operator
	 *
	 * @var string
	 */
	const ADD = "+";

	/**
	 * Subtraction operator
	 *
	 * @var string
	 */
	const SUB = "-";

	/**
	 * Multiplication operator
	 *
	 * @var string
	 */
	const MUL = "*";

	/**
	 * Division operator
	 *
	 * @var string
	 */
	const DIV = "/";

	/**
	 * Bitwise AND operator
	 *
	 * @var string
	 */
	const BIT_AND = "&";

	/**
	 * Bitwise OR operator
	 *
	 * @var string
	 */
	const BIT_OR = "|";

	/**
	 * Bitwise XOR operator
	 *
	 * @var string
	 */
	const BIT_XOR = "^";

	public readonly string $id;

	public readonly string $operand;

	/**
	 * Values used in this expression
	 *
	 * @var Sequelizable[]
	 */
	public readonly array $values;

	public function __construct(string $operand, Sequelizable ...$values) {
		$this -> id = "[f:" . randString(7) . "]";
		$this -> operand = $operand;
		$this -> values = $values;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		$parts = [];
		$params = [];

		foreach ($this -> values as $value) {
			[$s, $p] = $value -> sequelize($query);
			$parts[] = "({$s})";
			$params = array_merge($params, $p);
		}

		return [implode(" {$this -> operand} ", $parts), $params];
	}
}
