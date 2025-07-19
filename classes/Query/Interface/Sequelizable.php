<?php

namespace Blink\Query\Interface;

use Blink\Query;

interface Sequelizable {
	public function getID(): string;

	/**
	 * Sequelize this object into a queryable string
	 *
	 * @param	Query	$query
	 * @return	array	`[$query, $params]`
	 */
	public function sequelize(Query $query): array;
}
