<?php

namespace Blink\Query\Statement;

use Blink\Exception\CodingError;
use Blink\Query;
use Blink\Query\Expression\Expr;
use Blink\Query\Interface\Sequelizable;
use Blink\Query\QueryBuilder;
use function Blink\randString;

/**
 * Case statement using conditions
 *
 * https://dev.mysql.com/doc/refman/9.1/en/case.html
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class CaseCondition implements Sequelizable {

	public readonly string $id;

	public ?Sequelizable $else = null;

	/**
	 * Store all WHEN conditions
	 *
	 * @var QueryBuilder[]
	 */
	public array $whens = [];

	/**
	 * Store all THEN values
	 *
	 * @var Sequelizable[]
	 */
	public array $thens = [];

	/**
	 * Lock for waiting then statement.
	 *
	 * @var bool
	 */
	protected bool $waitingThen = false;

	/**
	 * Construct a new case statement, matching value.
	 *
	 * @link https://dev.mysql.com/doc/refman/9.1/en/case.html
	 */
	public function __construct() {
		$this -> id = "[s:cc:" . randString(7) . "]";
	}

	/**
	 * Add a new `WHEN ()` case to the list
	 *
	 * @param	mixed[]		$values
	 * @return	$this
	 */
	public function when(...$values): static {
		if ($this -> waitingThen)
			throw new CodingError("You need to complete the last <code>CaseCondition::when()</code> by calling <code>CaseCondition::then()</code>");

		$builder = new QueryBuilder();
		$builder -> whereWith($values);
		$this -> whens[] = $builder;
		$this -> waitingThen = true;
		return $this;
	}

	/**
	 * Add `THEN ()` value for the previous WHEN.
	 *
	 * @param	mixed		$value
	 * @return	$this
	 */
	public function then($value): static {
		if (!$this -> waitingThen)
			throw new CodingError("You need to call <code>CaseCondition::when()</code> before calling <code>CaseCondition::then()</code>");

		$this -> thens[] = Expr::processValue($value);
		$this -> waitingThen = false;
		return $this;
	}

	/**
	 * Set else value for this case statement
	 *
	 * @param	Sequelizable|string|int|float|boolean	$value
	 * @return	$this
	 */
	public function else($value): static {
		$this -> else = Expr::processValue($value);
		return $this;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		if ($this -> waitingThen)
			throw new CodingError("Case condition incomplete: The last <code>CaseCondition::when()</code> is not completed!");

		$cases = [];
		$params = [];

		foreach ($this -> whens as $index => $when) {
			[$ws, $wp] = $when -> sequelize($query);
			[$ts, $tp] = $this -> thens[$index] -> sequelize($query);
			$cases[] = "WHEN {$ws} THEN {$ts}";
			$params = array_merge($params, $wp, $tp);
		}

		if (!empty($this -> else)) {
			[$es, $ep] = $this -> else -> sequelize($query);
			$cases[] = "ELSE {$es}";
			$params = array_merge($params, $ep);
		}

		return [
			"CASE\n\t" . implode("\n\t", $cases) . "\nEND",
			$params
		];
	}
}
