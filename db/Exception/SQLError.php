<?php

namespace Blink\DB\Exception;
use Blink\Exception\BaseException;

class SQLError extends BaseException {
	public function __construct(int $code, string $description, string $query = null) {
		parent::__construct(SQL_ERROR, $description, 500, array(
			"code" => $code,
			"description" => $description,
			"query" => $query
		), $query);
	}
}