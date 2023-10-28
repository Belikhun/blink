<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;
use Blink\Router\Route;

/**
 * RouteArgumentMismatch.php
 *
 * Exceptions to tell that callback arguments does not match with request.
 *
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouteArgumentMismatch extends BaseException {
	/**
     * The Route object associated with the exception.
	 *
     * @var Route
     */
    public $route;

    /**
     * The error message associated with the exception.
     * @var string
     */
    public $message;

	public function __construct(Route $route, $message) {
		$this -> route = $route;
		$this -> message = $message;
		parent::__construct(DATA_TYPE_MISMATCH, $message, 400, (Array) $route);
	}
}
