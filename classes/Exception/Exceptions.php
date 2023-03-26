<?php

/**
 * Exceptions.php
 * 
 * Web core built in exceptions.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2022 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;

use Blink\Exception\BaseException;

class JSONDecodeError extends BaseException {
	public function __construct(String $file, String $message, $data) {
		$file = getRelativePath($file);
		parent::__construct(INVALID_JSON, "json_decode({$file}): {$message}", 500, Array( "file" => $file, "data" => $data ));
	}
}

class UnserializeError extends BaseException {
	public function __construct(String $file, String $message, $data) {
		$file = getRelativePath($file);
		$message = str_replace("unserialize(): ", "", $message);

		parent::__construct(47, "unserialize({$file}): {$message}", 500, Array( "file" => $file, "data" => $data ));
	}
}

class MissingParam extends BaseException {
	public function __construct(String $param) {
		parent::__construct(MISSING_PARAM, "missing required param: $param", 400, Array( "param" => $param ));
	}
}

class FileNotFound extends BaseException {
	public function __construct(String $path) {
		parent::__construct(FILE_MISSING, "File \"$path\" does not exist on this server.", 404, Array( "path" => $path ));
	}
}

class IllegalAccess extends BaseException {
	public function __construct(String $message = null) {
		parent::__construct(ACCESS_DENIED, $message ?: "You don't have permission to access this resource.", 403);
	}
}

class SQLError extends BaseException {
	public function __construct(int $code, String $description, String $query = null) {
		parent::__construct(SQL_ERROR, $description, 500, Array(
			"code" => $code,
			"description" => $description,
			"query" => $query
		));
	}
}

class CodingError extends BaseException {
	public function __construct($message) {
		parent::__construct(CODING_ERROR, $message, 500);
	}
}

class UserNotFound extends BaseException {
	public function __construct(Array $field) {
		$key = array_key_first($field);
		$value = $field[$key];

		parent::__construct(USER_NOT_FOUND, "Cannot find user with $key = $value", 404, $field);
	}
}

class NotLoggedIn extends BaseException {
	public function __construct() {
		parent::__construct(NOT_LOGGED_IN, "You are not logged in. Maybe your session expired?", 403);
	}
}

class RouteArgumentMismatch extends BaseException {
	public function __construct(\Router\Route $route, $message) {
		parent::__construct(DATA_TYPE_MISMATCH, $message, 400, (Array) $route);
	}
}

class InvalidToken extends BaseException {
	public function __construct() {
		parent::__construct(INVALID_TOKEN, "The token supplied is invalid or does not exist.", 403);
	}
}

class TokenExpired extends BaseException {
	public function __construct() {
		parent::__construct(TOKEN_EXPIRED, "The token supplied is expired.", 403);
	}
}

class InvalidURL extends BaseException {
	public function __construct(String $url) {
		parent::__construct(INVALID_URL, "The URL \"$url\" is invalid!", 400);
	}
}

class InvalidValue extends BaseException {
	public function __construct(String $value, String $type) {
		parent::__construct(INVALID_VALUE, "Giá trị \"$value\" không phải là một \"$type\" hợp lệ!", 400);
	}
}

class MaxLengthExceeded extends BaseException {
	public function __construct(String $field, int $max) {
		parent::__construct(MAX_LENGTH_EXCEEDED, "$field không được vượt quá $max kí tự!", 400);
	}
}

class FileInstanceNotFound extends BaseException {
	public function __construct(String $hash) {
		parent::__construct(FILE_INSTANCE_NOT_FOUND, "Không tồn tại tệp với mã $hash trong cơ sở dữ liệu!", 404);
	}
}

class SQLDriverNotFound extends BaseException {
	public function __construct(String $name) {
		parent::__construct(SQL_DRIVER_NOT_FOUND, "Không tồn tại driver SQL với tên \"$name\"!", 500);
	}
}

class InvalidSQLDriver extends BaseException {
	public function __construct(String $name) {
		parent::__construct(INVALID_SQL_DRIVER, "Driver SQL \"$name\" không hợp lệ! Có thể driver này chưa được định nghĩa hoặc chưa kế thừa từ \"\\DB\"!", 500);
	}
}

class DatabaseNotUpgraded extends BaseException {
	public function __construct(int $version, int $target) {
		parent::__construct(DATABASE_NOT_UPGRADED, "Cơ sở dữ liệu chưa được cập nhật! Phiên bản hiện tại là $version nhưng phiên bản của cấu trúc bảng là $target. Vui lòng cập nhật lại đoạn mã nâng cấp của bạn!", 500);
	}
}
