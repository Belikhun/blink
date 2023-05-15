<?php

/**
 * Exceptions.php
 * 
 * Web core built in exceptions.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
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

		parent::__construct(47, "unserialize(\"{$file}\"): {$message}", 500, Array( "file" => $file, "data" => $data ));
	}
}

class MissingParam extends BaseException {
	public function __construct(String $param) {
		parent::__construct(MISSING_PARAM, "Missing required param: $param", 400, Array( "param" => $param ));
	}
}

class IllegalAccess extends BaseException {
	public function __construct(String $message = null) {
		parent::__construct(ACCESS_DENIED, $message ?: "You don't have permission to access this resource.", 403);
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
