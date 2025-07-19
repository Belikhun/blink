<?php

namespace Blink\Query\Expression;

use Exception;
use Blink\Query;
use Blink\Exception\CodingError;
use Blink\DB\Exception\DatabaseColumnNotFound;
use Blink\DB\Exception\DatabaseTableNotFound;
use Blink\Query\Interface\Sequelizable;

/**
 * Represent a partial column in expression.
 *
 * Partial column will be validated during query instead of construction.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class PartialColumn implements Sequelizable {

	public readonly string $id;

	public readonly string $column;

	public readonly string $original;

	public ?string $table = null;

	public bool $validated = false;

	public bool $fallbackNativeValue = false;

	protected ?NativeValue $returnNativeValue = null;

	public ?string $tableAlias = null;

	public function __construct(string $column) {
		$this -> id = "[pc:{$column}]";
		$this -> original = $column;

		if ($column != "*" && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column))
			throw new \Blink\DB\Exception\DatabaseInvalidColumn($column);

		if (str_contains($column, ".")) {
			[$this -> table, $this -> column] = explode(".", $column);
		} else {
			$this -> table = null;
			$this -> column = $column;
		}
	}

	public function getID(): string {
		return $this -> id;
	}

	/**
	 * Validate this partial column in query time.
	 *
	 * TODO: refactor this shit
	 *
	 * @param	Query			$query
	 * @throws	CodingError
	 * @return	bool
	 */
	public function validate(Query $query) {
		$queryTable = $query -> getQueryingTable();

		if (!empty($this -> table) && $alias = $query -> getObjectByAlias($this -> table)) {
			$this -> tableAlias = $this -> table;

			if ($alias instanceof SubQuery) {
				$this -> validateColumn(query: $alias -> query);
			} else if ($alias instanceof SelectTable) {
				if ($alias -> table instanceof Query) {
					$this -> validateColumn(query: $alias -> table);
				} else {
					$this -> table = $alias -> table -> table;
					$this -> validateColumn();
				}
			} else if ($alias instanceof JoinTable) {
				$this -> table = $alias -> table -> table;
				$this -> validateColumn();
			} else if ($alias instanceof Table) {
				$this -> table = $alias -> table;
				$this -> validateColumn();
			} else {
				throw new CodingError("Invalid alias class: " . get_class($alias));
			}
		} else if ($queryTable -> table instanceof Query) {
			// We are targetting subquery column. Since we don't have any
			// column information available for it, we don't run validation against it.
			$this -> table = $queryTable -> alias;
			$this -> tableAlias = $this -> table;
			$this -> validated = true;
		} else {
			if (empty($this -> table))
				$this -> table = $queryTable -> table -> table;

			$this -> validateColumn();
			$this -> tableAlias = null;
		}

		$this -> returnNativeValue = null;
		return true;
	}

	public function validateColumn(bool $throw = true, ?Query $query = null) {
		global $DB;

		if (empty($this -> table))
			throw new CodingError("Column <code>{$this -> column}</code> does not have table name to perform validation");

		if ($this -> column == "*") {
			$this -> validated = true;
			return true;
		}

		if (!empty($query)) {
			if (!$query -> haveColumn($this -> column))
				throw new DatabaseColumnNotFound($this -> column, $this -> table);
		} else {
			$cols = $DB -> getColumns($this -> table);

			if (empty($cols)) {
				if ($throw)
					throw new DatabaseTableNotFound($this -> table);

				return false;
			}

			if (empty($cols[$this -> column])) {
				if ($throw)
					throw new DatabaseColumnNotFound($this -> column, $this -> table);

				return false;
			}
		}

		$this -> validated = true;
		return true;
	}

	public function getName() {
		if (empty($this -> table))
			return $this -> column;

		return "{$this -> table}.{$this -> column}";
	}

	public function out(bool $useAlias = true): string {
		if (empty($this -> table))
			return $this -> column;

		return ($this -> tableAlias && $useAlias)
			? "`{$this -> tableAlias}`.{$this -> column}"
			: "{{$this -> table}}.{$this -> column}";
	}

	public function sequelize(Query $query): array {
		if (!$this -> validated) {
			try {
				$this -> validate($query);
			} catch (Exception $e) {
				if ($this -> fallbackNativeValue) {
					if (empty($this -> returnNativeValue))
						$this -> returnNativeValue = Expr::value($this -> original);
				} else {
					throw $e;
				}
			}
		}

		if (!empty($this -> returnNativeValue))
			return $this -> returnNativeValue -> sequelize($query);

		return [$this -> out(!$query -> disableAlias), []];
	}

	public function __toString(): string {
		return $this -> out();
	}
}
