<?php

namespace Blink\Middleware\Exception;

use Blink\Exception\BaseException;

/**
 * Exception thrown when middleware class is not defined.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ClassNotDefined extends BaseException {
	/**
	 * Target class name that is not defined after
	 * autoloading middleware file.
	 * @var string
	 */
	public string $class;

	/**
	 * Target file that loaded during autoload process that
	 * does not define specified class.
	 * @var string
	 */
	public string $targetFile;

	public function __construct(string $class, string $file) {
		$this -> class = $class;
		$this -> targetFile = $file;

		parent::__construct(
			MIDDLEWARE_CLASS_MISSING,
			"Middleware class [{$class}] is missing after autoloaded!",
			500,
			array( "class" => $class, "file" => $file )
		);
	}
}
