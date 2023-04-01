<?php

/**
 * Route.php
 * 
 * Exceptions used in routing.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;
use Blink\Exception\BaseException;

class RouteArgumentMismatch extends BaseException {
	/**
     * The Route object associated with the exception.
     * @var \Router\Route
     */
    public $route;

    /**
     * The error message associated with the exception.
     * @var string
     */
    public $message;

	public function __construct(\Router\Route $route, $message) {
		$this -> route = $route;
		$this -> message = $message;
		parent::__construct(DATA_TYPE_MISMATCH, $message, 400, (Array) $route);
	}
}

class RouteNotFound extends BaseException {
	/**
	 * Requested URI path from user.
	 * @var string
	 */
	public $path;

	/**
	 * Requested URI method from user.
	 * @var string
	 */
	public $method;

	public function __construct(String $path, String $method) {
		$this -> path = $path;
		$this -> method = $method;
		parent::__construct(
			ROUTE_NOT_FOUND,
			"Cannot find route for {$method} \"$path\"",
			404,
			Array( "path" => $path, "method" => $method )
		);
	}
}
