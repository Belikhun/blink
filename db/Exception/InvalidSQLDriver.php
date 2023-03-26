<?php

namespace Blink\DB\Exception;
use Blink\Exception\BaseException;

class InvalidSQLDriver extends BaseException {
	public function __construct(String $name) {
		parent::__construct(INVALID_SQL_DRIVER, "Driver SQL \"$name\" không hợp lệ! Có thể driver này chưa được định nghĩa hoặc chưa kế thừa từ \"\\DB\"!", 500);
	}
}
