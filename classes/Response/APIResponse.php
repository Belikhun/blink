<?php
/**
 * APIResponse.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Response;
use Blink\Exception\BaseException;

class APIResponse extends JsonResponse {

	public int $code = 0;
	public String $description = "";
	protected Array|Object $data = Array();
	protected ?String $hash = null;
	private ?Array $output = null;

	public function __construct(
		int $code = 0,
		String $description = "Success!",
		Array|Object $data = Array(),
		bool|Array|String $hash = false
	) {
		$this -> code($code);
		$this -> description($description);
		$this -> data($data);
		$this -> hash($hash);
		$this -> header("Content-Type", "application/json; charset=utf-8");
	}

	public function code(int $code) {
		$this -> code = $code;
		return $this;
	}

	public function description(String $description) {
		// Remove absolute path.
		$this -> description = str_replace(BASE_PATH, "", $description);
		return $this;
	}

	public function data(Array|Object $data) {
		$this -> data = $data;
		return $this;
	}

	public function hash(bool|Array|String $hash) {
		if (is_bool($hash)) {
			if ($hash && (is_array($this -> data) || $this -> data instanceof \stdClass))
				$this -> hash = md5(serialize($this -> data));
		} else if (is_string($hash)) {
			$this -> hash = $hash;
		} else {
			$this -> hash = md5(serialize($hash));
		}
		
		return $this;
	}

	public function output(): Array {
		global $runtime, $ERROR_STACK;

		if (!empty($this -> output))
			return $this -> output;

		$data = $this -> data;
		$exceptionData = null;
		$caller = "hidden";
		$exception = null;
	
		if ($this -> data instanceof \Throwable) {
			$exception = $this -> data;
			$stacktrace = null;
			$additionalData = null;
	
			$ERROR_STACK[] = $exception;
			$file = getRelativePath($exception -> getFile());
	
			if (class_exists("CONFIG") && !\CONFIG::$PRODUCTION) {
				$stacktrace = processBacktrace($exception);
				$caller = $stacktrace[0] -> getCallString();
			}
	
			if ($exception instanceof BaseException)
				$additionalData = $exception -> data;
	
			$exceptionData = Array(
				"class" => get_class($exception),
				"file" => $file,
				"line" => $exception -> getLine(),
				"data" => $additionalData,
				"stacktrace" => $stacktrace
			);
	
			$data = ($exception instanceof BaseException)
				? $exception -> data
				: null;
		}
	
		$this -> output = Array(
			"code" => $this -> code,
			"status" => $this -> status,
			"description" => $this -> description,
			"caller" => "{$caller}()",
			"user" => class_exists("Session", true)
				? \Session::$username
				: null,
			"data" => $data,
			"hash" => $this -> hash,
			"runtime" => $runtime -> stop(),
			"exception" => $exceptionData
		);

		return $this -> output;
	}

	public function process(): String {
		$this -> json($this -> output());
		return parent::process();
	}
}