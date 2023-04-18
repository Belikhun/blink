<?php
/**
 * InvalidMiddlewareReturn.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Middleware\Exception;
use Blink\Exception\BaseException;

class InvalidMiddlewareReturn extends BaseException {
	/**
	 * The middleware clas name.
	 * @var string
	 */
	public String $middleware;

	/**
	 * The expected class name.
	 * @var string
	 */
	public String $expect;

	/**
	 * The class name we got instead.
	 * @var string
	 */
	public String $got;

	public function __construct(String $middleware, String $expect, String $got) {
		$this -> middleware = $middleware;
		$this -> expect = $expect;
		$this -> got = $got;

		parent::__construct(
			MIDDLEWARE_INVALID_RETURN,
			"Expected middleware [{$middleware}] to return instance of [{$expect}], got [{$got}] instead!",
			500,
			Array( "middleware" => $middleware, "expect" => $expect, "got" => $got )
		);
	}
}
