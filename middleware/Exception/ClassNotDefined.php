<?php
/**
 * ClassNotDefined.php
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

class ClassNotDefined extends BaseException {
	/**
	 * Target class name that is not defined after
	 * autoloading middleware file.
	 * @var string
	 */
	public String $class;

	public function __construct(String $class) {
		$this -> class = $class;

		parent::__construct(
			MIDDLEWARE_CLASS_MISSING,
			"Middleware class [{$class}] is missing after autoloaded!",
			500,
			Array( "class" => $class )
		);
	}
}