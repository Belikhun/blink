<?php
/**
 * InvalidDefinition.php
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

class InvalidDefinition extends BaseException {
	/**
	 * Target class name that is not defined after
	 * autoloading middleware file.
	 * @var string
	 */
	public string $class;

	/**
	 * Name of the class the middleware must extend from.
	 * @var string
	 */
	public string $from;

	/**
	 * The file containing invalid class definition.
	 * @var string
	 */
	public ?string $targetFile;

	public function __construct(string $class, string $from, string $file = null) {
		$this -> class = $class;
		$this -> from = $from;
		$this -> targetFile = $file;
		
		parent::__construct(
			MIDDLEWARE_CLASS_INVALID,
			"Middleware class [{$class}] is not valid! It's must be extended from [{$from}]",
			500,
			array( "class" => $class, "from" => $from, "file" => $file )
		);
	}
}
