<?php

namespace Blink\DB\Exception;
use Blink\Exception\BaseException;

class TableNotFound extends BaseException {
	public function __construct(String $name, String $query) {
		parent::__construct(SQL_TABLE_NOT_FOUND, "Table \"$name\" does not exist in database!", 500, Array(
			"name" => $name,
			"query" => $query
		));
	}
}
