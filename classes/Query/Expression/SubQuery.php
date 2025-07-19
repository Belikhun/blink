<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Represent a sub-query inside a normal query.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class SubQuery implements Sequelizable {

	public readonly string $id;

	public readonly Query $query;

	public function __construct(Query $query) {
		$this -> id = "[sq:" . randString(7) . "]";
		$this -> query = $query;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		[$sql, $params] = $this -> query -> makeSQLCall();
		return ["({$sql})", $params];
	}
}
