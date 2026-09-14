<?php

namespace Blink\Ticker\Exception;

use Blink\Exception\BaseException;

/**
 * A recurrence pattern could not be read.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidPattern extends BaseException {
	public string $pattern;

	public function __construct(string $pattern, string $reason) {
		$this -> pattern = $pattern;

		parent::__construct(
			TICKER_INVALID_PATTERN,
			"\"{$pattern}\" is not a valid recurrence pattern.",
			500,
			array( "pattern" => $pattern, "reason" => $reason ),
			$reason
		);
	}
}
