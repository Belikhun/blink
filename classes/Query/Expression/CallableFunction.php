<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Callable sql function.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class CallableFunction implements Sequelizable {

	public readonly string $id;

	/**
	 * Function name
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Function all arguments
	 *
	 * @var Sequelizable[]
	 */
	public readonly array $arguments;

	public function __construct(string $name, Sequelizable ...$arguments) {
		$this -> id = "[f:" . randString(7) . "]";
		$this -> name = strtoupper($name);
		$this -> arguments = $arguments;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		$args = [];
		$params = [];

		foreach ($this -> arguments as $argument) {
			[$s, $p] = $argument -> sequelize($query);
			$args[] = $s;
			$params = array_merge($params, $p);
		}

		return ["{$this -> name}(" . implode(", ", $args) . ")", $params];
	}

	public function __toString(): string {
		return "{$this -> name}()";
	}
}
