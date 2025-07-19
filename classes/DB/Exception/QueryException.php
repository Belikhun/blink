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
                "state" => $state
            ),
            sql: $sql,
            params: $params
        );
    }
}
