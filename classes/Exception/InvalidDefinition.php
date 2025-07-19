<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * Exception raised when a class is not correctly defined by not extending a required parent class.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidDefinition extends BaseException {
	/**
	 * Target class name that is not defined after
	 * autoloading.
	 * 
	 * @var string
	 */
	public string $class;

	/**
	 * Name of the class must extend from.
	 * 
	 * @var string
	 */
	public string $from;

	/**
	 * The file containing invalid class definition.
	 * 
	 * @var string
	 */
	public ?string $targetFile;

	public function __construct(string $class, string $from, string $file = null) {
		$this -> class = $class;
		$this -> from = $from;
		$this -> targetFile = $file;
		
		parent::__construct(
			AUTOLOAD_CLASS_INVALID,
			"Class <code>{$class}</code> is not valid! It's must be extended from <code>{$from}</code>",
			500,
			array( "class" => $class, "from" => $from, "file" => $file )
		);
	}
}
