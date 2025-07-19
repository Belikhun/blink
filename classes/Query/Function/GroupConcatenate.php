<?php

namespace Blink\Query\Function;

use Blink\Query;
use Blink\Query\Expression\Column;
use Blink\Query\Expression\Expr;
use Blink\Query\Expression\NativeValue;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Returns a string result with the concatenated non-NULL values from a group.
 *
 * https://dev.mysql.com/doc/refman/9.3/en/aggregate-functions.html#function_group-concat
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class GroupConcatenate implements Sequelizable {

	public readonly string $id;

	public Column $column;

	public NativeValue $separator;

	public bool $distinct = false;

	/**
	 * Construct a new `GROUP_CONCAT()` function.
	 *
	 * https://dev.mysql.com/doc/refman/9.3/en/aggregate-functions.html#function_group-concat
	 */
	public function __construct(Column $column, string $separator, bool $distinct = false) {
		$this -> id = "[f:gc:" . randString(7) . "]";
		$this -> column = $column;
		$this -> separator = Expr::value($separator);
		$this -> distinct = $distinct;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		$sql = "GROUP_CONCAT(";
		$params = [];

		if ($this -> distinct)
			$sql .= "DISTINCT ";

		[$cs, $cp] = $this -> column -> sequelize($query);
		$sql .= $cs;
		$params = array_merge($params, $cp);

		[$ss, $sp] = $this -> separator -> sequelize($query);
		$sql .= " SEPARATOR {$ss})";
		$params = array_merge($params, $sp);
		return [$sql, $params];
	}
}
