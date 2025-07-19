<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

class TableNotFound extends BaseException {
	public function __construct(string $name, string $query) {
		parent::__construct(SQL_TABLE_NOT_FOUND, "Table \"$name\" does not exist in database!", 500, array(
			"name" => $name,
			"query" => $query
		));
	}
}
