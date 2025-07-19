<?php

namespace Blink\Exception;

/**
 * User instance is not found with given conditions.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class UserNotFound extends BaseException {
	public function __construct(array $field) {
		$key = array_key_first($field);
		$value = $field[$key];

		parent::__construct(USER_NOT_FOUND, "Cannot find user with {$key} = {$value}", 404, $field);
	}
}
