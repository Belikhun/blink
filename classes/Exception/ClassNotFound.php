<?php
/**
 * ClassNotFound.php
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

namespace Blink\Exception;

class ClassNotFound extends BaseException {
	/**
	 * Target class name that does not exist after
	 * autoloading target file.
	 * @var string
	 */
	public String $class;

	public function __construct(String $class) {
		$this -> class = $class;

		parent::__construct(
			CLASS_NOT_FOUND,
			"Class [{$class}] not found!",
			500,
			Array( "class" => $class )
		);
	}
}
