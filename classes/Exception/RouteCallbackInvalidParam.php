<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * RouteCallbackInvalidParam.php
 *
 * Exception for param name mismatching with callback argument name.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouteCallbackInvalidParam extends BaseException {
	/**
	 * Route URI
	 *
	 * @var string
	 */
	public string $uri;

	/**
	 * Param name defined in route callback
	 *
	 * @var string
	 */
	public string $param;

	public function __construct(string $uri, string $param) {
		$this -> uri = $uri;
		$this -> param = $param;

		parent::__construct(
			ROUTE_CALLBACK_INVALID_PARAM,
			"Callback for route \"{$uri}\" is requesting unknown URI parameter: \"{$param}\"",
			500,
			array( "uri" => $uri, "param" => $param )
		);
	}
}
