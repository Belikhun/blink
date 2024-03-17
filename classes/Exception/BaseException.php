<?php

namespace Blink\Exception;

/**
 * BaseException.php
 * 
 * Base exception class for blink.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

class BaseException extends \Exception {
	/**
	 * Error Code
	 *
	 * @var	int
	 */
	public $code;

	/**
	 * Error Description/Message
	 *
	 * @var	string
	 */
	public String $description;

	/**
	 * HTTP Status Code
	 *
	 * @var	int
	 */
	public int $status;

	/**
	 * Optional error data.
	 *
	 * @var	array|\stdClass
	 */
	public $data;

	/**
	 * Optional error details.
	 *
	 * @var	?string
	 */
	public ?String $details = null;

	/**
	 * Custom trace data of this error.
	 *
	 * @var	array
	 */
	protected Array $trace = [];

	/**
	 * The file that generated this exception.
	 *
	 * @var	string
	 */
	public String $file;

	/**
	 * The line of the file that generated this exception.
	 *
	 * @var int
	 */
	public int $line;

	/**
	 * Exception class designed for detailed error report.
	 *
	 * @param	int					$code			Error Code
	 * @param	string				$description	Error Description/Message
	 * @param	int					$status			HTTP Status Code
	 * @param	array|\stdClass		$data			Optional Error Data
	 */
	public function __construct(
		int $code,
		String $description,
		int $status = 500,
		Array|\stdClass $data = null,
		String $details = null
	) {
		$this -> code = $code;
		$this -> description = $description;
		$this -> status = $status;
		$this -> data = $data;
		$this -> details = $details;
		parent::__construct($description, $code);

		$this -> file = getRelativePath(parent::getFile());
		$this -> line = parent::getLine();
	}

	public function applyFrom(\Throwable $e) {
		$this -> file = getRelativePath($e -> getFile());
		$this -> line = $e -> getLine();
		$this -> trace = $e -> getTrace();
	}

	public function trace() {
		if (empty($this -> trace))
			return parent::getTrace();

		return $this -> trace;
	}

	public function __toString() {
		return "HTTP {$this -> status} ({$this -> code}) ". get_class($this) .": {$this -> description}";
	}
}
