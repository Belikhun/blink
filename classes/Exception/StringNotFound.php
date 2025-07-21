<?php

namespace Blink\Exception;

/**
 * String not found exception
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class StringNotFound extends BaseException {
	public function __construct(string $identifier) {
		parent::__construct(
			STRING_NOT_FOUND,
			"String with identifier <code>{$identifier}</code> is not found!",
			500,
			array( "identifier" => $identifier )
		);
	}
}
