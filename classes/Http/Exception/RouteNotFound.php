<?php

namespace Blink\Http\Exception;

use Blink\Exception\BaseException;

/**
 * Exception thrown when no route found that can handle current request.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouteNotFound extends BaseException {
	/**
	 * Requested URI path from user.
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Requested URI method from user.
	 *
	 * @var string
	 */
	public $method;

	public function __construct(string $path, string $method) {
		$this -> path = $path;
		$this -> method = $method;
		parent::__construct(
			ROUTE_NOT_FOUND,
			"Cannot find route for {$method} <code>{$path}</code>",
			404,
			array( "path" => $path, "method" => $method )
		);
	}
}
