<?php

namespace Blink\DB\Exception;

class QueryException extends DatabaseException {
	public function __construct(
		int $code,
		string $message,
		?string $state = null,
		?string $sql = null,
		?array $params = null
	) {
		parent::__construct(
			"An exception occured while executing SQL statement",
			$message,
			array(
				"code" => $code,
				"state" => $state,

				// The driver's own wording, kept apart from the description a
				// user sees so the driver can match on it later. Telling a
				// unique violation from any other constraint failure is the
				// one case that needs it.
				"message" => $message
			),
			sql: $sql,
			params: $params
		);
	}
}
