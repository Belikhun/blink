<?php

namespace Blink\Query\Expression;

use Blink\Exception\CodingError;
use Blink\Query;
use Blink\Query\Expression\Table;
use Blink\Query\Interface\SequelizableWithAlias;

/**
 * Represent a table in select part of query.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class SelectTable implements SequelizableWithAlias {

	public readonly string $id;

	public readonly Table|Query $table;

	public ?string $alias = null;

	public function __construct(Table|Query $table, string $alias = null) {
		if ($table instanceof Query && empty($alias))
			throw new CodingError("Table alias is required when using subquery!");

		$this -> id = ($table instanceof Query)
			? "[q:{$alias}]"
			: $table -> getID();

		$this -> table = $table;
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
		if ($this -> table instanceof Query) {
			[$sql, $params] = $this -> table -> makeSQLCall();
			return ["({$sql}) AS `{$this -> alias}`", $params];
		}

		$table = $this -> table -> out();

		if (!empty($this -> alias) && !$query -> disableAlias)
			return ["{$table} AS `{$this -> alias}`", []];

		return [$table, []];
	}
}
