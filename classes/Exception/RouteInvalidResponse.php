<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * RouteInvalidResponse.php
 *
 * Indicate that route response is invalid.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
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
			"Callback for route \"{$uri}\" must return either String, Number or Blink\Response, got [{$got}]",
			500,
			array( "uri" => $uri, "got" => $got )
		);
	}
}
