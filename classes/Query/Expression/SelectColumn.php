<?php

namespace Blink\Query\Expression;

use Blink\Exception\CodingError;
use Blink\Query;
use Blink\Query\Expression\Column;
use Blink\Query\Interface\Sequelizable;
use Blink\Query\Interface\SequelizableWithAlias;

/**
 * Represent a column in select part of query.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class SelectColumn implements SequelizableWithAlias {

	public readonly string $id;

	public readonly Column|Sequelizable $column;

	public ?string $alias = null;

	/**
	 * Flag to enable selecting over calculated data window.
	 *
	 * TODO: Complete implementation of this function
	 *
	 * @var		bool
	 * @link	https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html
	 */
	public bool $over = false;

	public function __construct(Column|Sequelizable $column, string $alias = null) {
		// An expression has no name of its own, and a row has to be read
		// under one. A caller who names it gets that name; one who does not
		// gets a generated one rather than an error, since a select whose
		// value is read positionally, a subquery used as a value most of all,
		// never needs to know it.
		if (!($column instanceof Column || $column instanceof PartialColumn) && empty($alias))
			$alias = "expr_" . substr(md5($column -> getID()), 0, 8);

		$this -> id = $column -> getID();
		$this -> column = $column;
		$this -> alias = $alias;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function getAlias(): ?string {
		return $this -> alias;
	}

	public function setAlias(?string $alias = null): static {
		$this -> alias = $alias;
		return $this;
	}

	public function sequelize(Query $query): array {
		if ($this -> column instanceof Column) {
			$column = $this -> column -> out();

			if ($table = $query -> getAlias($this -> column -> table -> id))
				$column = "{$table}.{$this -> column -> column}";

			// @TODO: Complete implementation of all window functions.
			// https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html
			if ($this -> over)
				$column .= " OVER()";

			if (!empty($this -> alias))
				$column .= " AS `{$this -> alias}`";

			return [$column, []];
		}

		[$column, $params] = $this -> column -> sequelize($query);

		if ($this -> over)
			$column .= " OVER()";

		if (!empty($this -> alias))
			$column .= " AS `{$this -> alias}`";

		return [$column, $params];
	}
}
