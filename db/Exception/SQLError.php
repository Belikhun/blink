<?php

namespace Blink\DB\Exception;
use Blink\Exception\BaseException;

class SQLError extends BaseException {
	public function __construct(int $code, String $description, String $query = null) {
		parent::__construct(SQL_ERROR, $description, 500, Array(
			"code" => $code,
			"description" => $description,
			"query" => $query
		), $query);
	}
}