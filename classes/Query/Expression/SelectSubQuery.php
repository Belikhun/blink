<?php

namespace Blink\Query\Expression;

use Blink\Exception\CodingError;
use Blink\Query;
use Blink\Query\Interface\SequelizableWithAlias;

/**
 * Represent a sub-query inside select/join statements.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class SelectSubQuery implements SequelizableWithAlias {

	public readonly string $id;

	public readonly Query $query;

	public string $alias;

	public function __construct(Query $query, string $alias) {
		$this -> id = "[q:{$alias}]";
		$this -> query = $query;
		$this -> alias = $alias;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function getAlias(): string {
		return $this -> alias;
	}

	public function setAlias(?string $alias = null): static {
		throw new CodingError("Cannot change alias of a select subquery!");
	}

	public function sequelize(Query $query): array {
		[$sql, $params] = $this -> query -> makeSQLCall();
		return ["({$sql}) \"{$this -> alias}\"", $params];
	}
}
