<?php

namespace Blink\Exception;

/**
 * Value length of a field has exceeded the maximum amount allowed.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class MaxLengthExceeded extends BaseException {
	public function __construct(string $field, int $max) {
		parent::__construct(
			MAX_LENGTH_EXCEEDED,
			"<code>{$field}</code> cannot longer than <code>{$max}</code> characters!",
			400
		);
	}
}
