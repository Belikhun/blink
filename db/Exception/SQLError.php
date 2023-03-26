<?php

namespace Blink\DB\Exception;
use Blink\Exception\BaseException;

class SQLDriverNotFound extends BaseException {
	public function __construct(String $name) {
		parent::__construct(SQL_DRIVER_NOT_FOUND, "Không tồn tại driver SQL với tên \"$name\"!", 500);
	}
}
