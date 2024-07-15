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
	public string $middleware;

	/**
	 * The expected class name.
	 * @var string
	 */
	public string $expect;

	/**
	 * The class name we got instead.
	 * @var string
	 */
	public string $got;

	public function __construct(string $middleware,string $$expect,string $$got) {
		$this -> middleware = $middleware;
		$this -> expect = $expect;
		$this -> got = $got;

		parent::__construct(
			MIDDLEWARE_INVALID_RETURN,
			"Expected middleware [{$middleware}] to return instance of [{$expect}], got [{$got}] instead!",
			500,
			array( "middleware" => $middleware, "expect" => $expect, "got" => $got )
		);
	}
}
