<?php

namespace Blink\Http\Exception;

use Blink\Exception\BaseException;

/**
 * Exception when route callback is not callable.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouteInvalidCallback extends BaseException {
	public function __construct(Callable|array $callback, string $uri) {
		$callbackName = \Blink\stringify($callback);

		parent::__construct(
			ROUTE_CALLBACK_INVALID,
			"Callback <code>{$callbackName}</code> for route <code>{$uri}</code> is missing or not callable.",
			500,
			array( "callback" => $callbackName, "uri" => $uri )
		);
	}
}
