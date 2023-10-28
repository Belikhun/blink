<?php
/**
 * Route.php
 * 
 * Exceptions used in routing.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;
use Blink\Exception\BaseException;
use Blink\Router\Route;

class RouteArgumentMismatch extends BaseException {
	/**
     * The Route object associated with the exception.
	 * 
     * @var Route
     */
    public $route;

    /**
     * The error message associated with the exception.
	 * 
     * @var string
     */
    public $message;

	public function __construct(Route $route, $message) {
		$this -> route = $route;
		$this -> message = $message;
		parent::__construct(DATA_TYPE_MISMATCH, $message, 400, (Array) $route);
	}
}

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

class RouteCallbackInvalidParam extends BaseException {
	/**
	 * Route URI
	 * 
	 * @var string
	 */
	public String $uri;

	/**
	 * Param name defined in route callback
	 * 
	 * @var string
	 */
	public String $param;

	public function __construct(String $uri, String $param) {
		$this -> uri = $uri;
		$this -> param = $param;

		parent::__construct(
			ROUTE_CALLBACK_INVALID_PARAM,
			"Callback for route \"{$uri}\" is requesting unknown URI parameter: \"{$param}\"",
			500,
			Array( "uri" => $uri, "param" => $param )
		);
	}
}

class RouteInvalidResponse extends BaseException {
	/**
	 * Route URI
	 * 
	 * @var string
	 */
	public String $uri;

	public String $got;

	public function __construct(String $uri, String $got) {
		$this -> uri = $uri;
		$this -> got = $got;

		parent::__construct(
			ROUTE_INVALID_RESPONSE,
			"Callback for route \"{$uri}\" must return either String, Number or <code>Blink\Response</code>, got <code>{$got}</code>",
			500,
			Array( "uri" => $uri, "got" => $got )
		);
	}
}
