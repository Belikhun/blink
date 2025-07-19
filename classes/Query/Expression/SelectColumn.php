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
		if (!($column instanceof Column || $column instanceof PartialColumn) && empty($alias))
			throw new CodingError("Column alias is required when selecting with function calls or using arithmetic operators (<code>{$column}</code>)");

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
