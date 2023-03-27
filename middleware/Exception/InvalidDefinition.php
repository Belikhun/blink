<?php
/**
 * InvalidDefinition.php
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Middleware\Exception;
use Blink\Exception\BaseException;

class InvalidDefinition extends BaseException {
	/**
	 * Target class name that is not defined after
	 * autoloading middleware file.
	 * @var string
	 */
	public String $class;

	/**
	 * Name of the class the middleware must extend from.
	 * @var string
	 */
	public String $from;

	public function __construct(String $class, String $from) {
		$this -> class = $class;
		$this -> from = $from;
		
		parent::__construct(
			MIDDLEWARE_CLASS_MISSING,
			"Middleware class [{$class}] is not valid! It's must extend from [{$from}]",
			500,
			Array( "class" => $class, "from" => $from )
		);
	}
}
