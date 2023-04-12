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

namespace Blink\Exception;
use Blink\Exception\BaseException;

class InvalidDefinition extends BaseException {
	/**
	 * Target class name that is not defined after
	 * autoloading.
	 * @var string
	 */
	public String $class;

	/**
	 * Name of the class must extend from.
	 * @var string
	 */
	public String $from;

	/**
	 * The file containing invalid class definition.
	 * @var string
	 */
	public ?String $targetFile;

	public function __construct(String $class, String $from, String $file = null) {
		$this -> class = $class;
		$this -> from = $from;
		$this -> targetFile = $file;
		
		parent::__construct(
			AUTOLOAD_CLASS_INVALID,
			"Class [{$class}] is not valid! It's must be extended from [{$from}]",
			500,
			Array( "class" => $class, "from" => $from, "file" => $file )
		);
	}
}
