<?php

namespace Blink\Query;

use Blink\Query;
use Blink\Query\Expression\Column;
use Blink\Query\Expression\PartialColumn;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Set value for update statement
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class QueryUpdateSet implements Sequelizable {

	public readonly string $id;

	public readonly Column|PartialColumn $column;

	public readonly Sequelizable $value;

	public string $direction = "ASC";

	public function __construct(Column|PartialColumn $column, Sequelizable $value) {
		$this -> id = "[us:" . randString(7) . "]";
		$this -> column = $column;
		$this -> value = $value;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		// The assignment target is written bare. SQLite rejects a qualified
		// name on the left of SET, and an UPDATE only ever assigns to the
		// table it names, so there is nothing to qualify it against.
		$column = $this -> column -> column;
		[$value, $params] = $this -> value -> sequelize($query);

		return ["{$column} = {$value}", $params];
	}
}
