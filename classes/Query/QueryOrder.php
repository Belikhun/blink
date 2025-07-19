<?php

namespace Blink\Query;

use Blink\Exception\CodingError;
use Blink\Query;
use Blink\Query\Expression\Column;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Query ordering
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class QueryOrder implements Sequelizable {

	public readonly string $id;

	public readonly Column|Sequelizable|string $column;

	public string $direction = "ASC";

	public function __construct(Column|Sequelizable|string $column, string $direction = "ASC") {
		$direction = strtoupper($direction);

		if ($direction !== "ASC" && $direction !== "DESC") {
			throw new CodingError(
				"Invalid query sort order directive for column <code>{$column}</code>",
				"Expected sort direction to be <code>[ASC, DESC]</code>, got <code>{$direction}</code> instead."
			);
		}

		$this -> id = "[f:" . randString(7) . "]";
		$this -> column = $column;
		$this -> direction = $direction;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		global $CFG;
		$by = $this -> column;

		if ($by instanceof Sequelizable) {
			[$s, $p] = $by -> sequelize($query);
			return ["{$s} {$this -> direction}", $p];
		}

		// Support selecting random rows.
		// See more: https://stackoverflow.com/questions/580639/how-to-randomly-select-rows-in-sql
		if ($by === Query::ORDER_RANDOM || $by === "RANDOM()" || $by === "RAND()") {
			switch ($CFG -> dbtype) {
				case "pgsql":
					$by = "RANDOM()";
					break;

				case "mariadb":
				case "mysqli":
				case "auroramysql":
					$by = "RAND()";
					break;

				case "sqlsrv":
					$by = "NEWID()";

				default:
					throw new CodingError("Selecting random rows is not supported when using database type <code>{$CFG -> dbtype}</code>!");
			}

			return ["{$by} {$this -> direction}", []];
		}

		if (is_string($by))
			$by = $query -> getColumn($by);

		if ($column = $query -> getAlias($by -> id))
			return ["\"{$column}\" {$this -> direction}", []];

		[$s, $p] = $by -> sequelize($query);
		return ["{$s} {$this -> direction}", $p];
	}
}
