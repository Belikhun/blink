<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * List of sequelizable values
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class SequelizableList implements Sequelizable {

	public readonly string $id;

	/**
	 * List of sequelizable values
	 *
	 * @var Sequelizable[]
	 */
	public readonly array $values;

	public function __construct(array $values) {
		$this -> id = "[sl:" . randString(7) . "]";
		$this -> values = $values;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		$sql = array();
		$params = array();

		foreach ($this -> values as $value) {
			[$s, $p] = $value -> sequelize($query);
			$sql[] = $s;
			$params = array_merge($params, $p);
		}

		$sql = implode(", ", $sql);
		return ["({$sql})", $params];
	}
}
