<?php

namespace Blink\Query\Expression;

use Blink\Exception\CodingError;
use Blink\Query;
use Blink\Query\Interface\SequelizableWithAlias;
use Blink\Query\QueryBuilder;

/**
 * Join expression
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class JoinTable implements SequelizableWithAlias {

	public readonly string $id;

	public readonly Table|Query $table;

	public ?string $alias = null;

	/**
	 * Join type
	 *
	 * @var		string		`Query::JOIN_*`
	 */
	public string $type = Query::JOIN_INNER;

	public QueryBuilder $condition;

	public function __construct(Table|Query $table, string $alias = null) {
		if ($table instanceof Query && empty($alias))
			throw new CodingError("Table alias is required when using subquery!");

		$this -> id = ($table instanceof Query)
			? "[q:{$alias}]"
			: $table -> getID();

		$this -> table = $table;
		$this -> alias = $alias;
		$this -> condition = new QueryBuilder();
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
		list($conditions, $params) = $this -> condition -> sequelize($query);

		if ($this -> table instanceof Query) {
			[$subQuery, $subParams] = $this -> table -> makeSQLCall();

			return [
				"{$this -> type} ({$subQuery}) `{$this -> alias}` ON ({$conditions})",
				array_merge($params, $subParams)
			];
		}

		$table = $this -> table -> out();

		if (!empty($this -> alias))
			$table = "{$table} `{$this -> alias}`";

		return ["{$this -> type} {$table} ON ({$conditions})", $params];
	}
}
