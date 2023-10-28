<?php

namespace Blink\Exception;

/**
 * ClassNotFound.php
 * 
 * A class that is not found in the {@link `CONFIG::$INCLUDES`} path.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ClassNotFound extends BaseException {
	/**
	 * Target class name that does not exist after
	 * autoloading target file.
	 * 
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
