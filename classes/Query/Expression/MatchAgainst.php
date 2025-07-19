<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Full text search's match-against expression.
 *
 * All used columns MUST have `FULLTEXT` index enabled.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class MatchAgainst implements Sequelizable {

	public readonly string $id;

	/**
	 * Columns used inside `MATCH` expression
	 *
	 * @var	Column[]
	 */
	public array $columns;

	public NativeValue $text;

	/**
	 * Construct a new full text search's match-against expression.
	 *
	 * | ⚠ All used columns MUST have `FULLTEXT` index enabled.
	 *
	 * @param	Column[]				$columns		Columns used inside `MATCH` expression
	 * @param	NativeValue|string		$text			Search text used inside `AGAINST` expression
	 */
	public function __construct(array $columns, NativeValue|string $text) {
		$this -> id = "[fts:" . randString(7) . "]";
		$this -> columns = $columns;
		$this -> text = Expr::value($text);
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		$columns = [];
		$params = [];

		foreach ($this -> columns as $column) {
			[$s, $p] = $column -> sequelize($query);
			$columns[] = $s;
			$params = array_merge($params, $p);
		}

		[$text, $textParam] = $this -> text -> sequelize($query);
		$params = array_merge($params, $textParam);

		return ["MATCH (" . implode(", ", $columns) . ") AGAINST ({$text})", $params];
	}
}
