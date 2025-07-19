<?php

namespace Blink\Http\Exception;

use Blink\Exception\BaseException;

/**
 * Exception thrown when route is returning an invalid response data.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouteInvalidResponse extends BaseException {
	/**
	 * Route URI
	 *
	 * @var string
	 */
	public string $uri;

	public string $got;

	public function __construct(string $uri, string $got) {
		$this -> uri = $uri;
		$this -> got = $got;

		parent::__construct(
			ROUTE_INVALID_RESPONSE,
			"Callback for route \"{$uri}\" must return either <code>string</code>, <code>number</code> or <code>Blink\Http\Response</code>, got <code>{$got}</code>",
			500,
			array( "uri" => $uri, "got" => $got )
		);
	}
}
