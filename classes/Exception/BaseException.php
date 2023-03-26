<?php

/**
 * BaseException.php
 * 
 * Base exception class for blink.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2022 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;

class BaseException extends \Exception {
	/**
	 * Error Code
	 * @var	int
	 */
	public $code;

	/**
	 * Error Description/Message
	 * @var	string
	 */
	public $description;

	/**
	 * HTTP Status Code
	 * @var	int
	 */
	public $status;

	/**
	 * Optional Error Data
	 * @var	array|\stdClass
	 */
	public $data;

	/**
	 * Exception class designed for detailed error report.
	 * 
	 * @param	int					$code			Error Code
	 * @param	string				$description	Error Description/Message
	 * @param	int					$status			HTTP Status Code
	 * @param	array|\stdClass		$data			Optional Error Data
	 */
	public function __construct(int $code, string $description, int $status = 500, array|\stdClass $data = null) {
		$this -> code = $code;
		$this -> description = $description;
		$this -> status = $status;
		$this -> data = $data;
		parent::__construct($description, $code);

		$this -> data["file"] = getRelativePath(parent::getFile());
		$this -> data["line"] = parent::getLine();
		$this -> data["trace"] = parent::getTrace();
	}

	public function __toString() {
		return "HTTP {$this -> status} ({$this -> code}) ". get_class($this) .": {$this -> description}";
	}
}
