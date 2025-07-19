<?php

namespace Blink\Query\Statement;

use Blink\Query;
use Blink\Query\Expression\Expr;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Case statement using value
 *
 * https://dev.mysql.com/doc/refman/9.1/en/case.html
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class CaseValue implements Sequelizable {

	public readonly string $id;

	public Sequelizable $value;

	public ?Sequelizable $else = null;

	/**
	 * Store all WHEN values
	 *
	 * @var Sequelizable[]
	 */
	public array $whens = [];

	/**
	 * Store all THEN values
	 *
	 * @var Sequelizable[]
	 */
	public array $thens = [];

	/**
	 * Construct a new case statement, matching value.
	 *
	 * @link https://dev.mysql.com/doc/refman/9.1/en/case.html
	 */
	public function __construct($value) {
		$this -> id = "[s:cv:" . randString(7) . "]";
		$this -> value = Expr::processValue($value);
	}

	/**
	 * Add a new `WHEN () THEN ()` case to the list
	 *
	 * @param	Sequelizable|string|int|float|boolean	$when
	 * @param	Sequelizable|string|int|float|boolean	$then
	 * @return	$this
	 */
	public function when($when, $then): static {
		$this -> whens[] = Expr::processValue($when);
		$this -> thens[] = Expr::processValue($then);
		return $this;
	}

	/**
	 * Set else value for this case statement
	 *
	 * @param	Sequelizable|string|int|float|boolean	$value
	 * @return	$this
	 */
	public function else($value): static {
		$value = Expr::processValue($value);
		$this -> else = $value;
		return $this;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
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

		[$vs, $vp] = $this -> value -> sequelize($query);

		return [
			"CASE {$vs}\n\t" . implode("\n\t", $cases) . "\nEND",
			array_merge($vp, $params)
		];
	}
}
