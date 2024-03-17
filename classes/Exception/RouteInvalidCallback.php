<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * RouteInvalidCallback.php
 *
 * Exception when route callback is not callable.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouteInvalidCallback extends BaseException {
	public function __construct(Callable|Array $callback, String $uri) {
		$callbackName = stringify($callback);

		parent::__construct(
			ROUTE_CALLBACK_INVALID,
			"Callback <code>{$callbackName}</code> for route <code>{$uri}</code> is missing or not callable.",
			500,
			Array( "callback" => $callbackName, "uri" => $uri )
		);
	}
}
