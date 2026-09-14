<?php

namespace Blink\Ticker\Exception;

use Blink\Exception\BaseException;

/**
 * `CONFIG::$TICKER_STORE` names a store that does not exist.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class StoreNotFound extends BaseException {
	public string $store;

	public function __construct(string $store, array $available) {
		$this -> store = $store;

		parent::__construct(
			TICKER_STORE_NOT_FOUND,
			"There is no ticker store named \"{$store}\".",
			500,
			array( "store" => $store, "available" => $available ),
			"Available stores: " . implode(", ", $available) . "."
		);
	}
}
