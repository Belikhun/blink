<?php

namespace Blink\Http\Response;

use Blink\Exception\BaseException;
use Blink\Session;
use function Blink\getRelativePath;
use function Blink\processBacktrace;

/**
 * Return formatted API response for use with `belibrary`.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class APIResponse extends JsonResponse {

	/**
	 * Response code. Mapped in `consts.php`
	 * 
	 * @var	int
	 */
	public int $code = 0;

	/**
	 * Response description. This will tell developer most of the information about the request.
	 * 
	 * @var	string
	 */
	public string $description = "";

	/**
	 * Additional data passed to the client.
	 * 
	 * @var	array|object|null
	 */
	protected array|object|null $data = array();

	/**
	 * Data computed hash. Used to indicate that changes happend to response data.
	 * 
	 * @var	?string
	 */
	protected ?string $hash = null;

	/**
	 * Generated output data, to make sure output does not get computed twice.
	 * 
	 * @var	array
	 */
	private ?array $output = null;

	public function __construct(
		int $code = 0,
		string $description = "Success!",
		int $status = 200,
		array|object $data = array(),
		bool|array|string $hash = false
	) {
		$this -> code($code)
			-> description($description)
			-> status($status)
			-> data($data)
			-> hash($hash)
			-> header("Content-Type", "application/json; charset=utf-8");
	}

	public function code(int $code) {
		$this -> code = $code;
		return $this;
	}

	public function description(string $description) {
		// Remove absolute path.
		$this -> description = str_replace(BASE_PATH, "", $description);
		return $this;
	}

	public function data(array|object $data) {
		$this -> data = $data;
		return $this;
	}

	public function hash(bool|array|string $hash) {
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

	public function output(): array {
		global $RUNTIME, $ERROR_STACK;

		if (!empty($this -> output))
			return $this -> output;

		$exceptionData = null;
		$caller = "hidden";
		$exception = null;
		$details = null;
	
		if ($this -> data instanceof \Throwable) {
			/** @var \Throwable */
			$exception = $this -> data;

			$stacktrace = null;
			$additionalData = null;
	
			$ERROR_STACK[] = $exception;
			$file = getRelativePath($exception -> getFile());
	
			if (class_exists("CONFIG") && \CONFIG::$DEBUG) {
				$stacktrace = processBacktrace($exception);
				$caller = $stacktrace[0] -> getCallString();
			}
	
			if ($exception instanceof BaseException) {
				$additionalData = $exception -> data;
				$details = $exception -> details;
			}
	
			$exceptionData = array(
				"class" => get_class($exception),
				"file" => $file,
				"line" => $exception -> getLine(),
				"data" => $additionalData,
				"stacktrace" => $stacktrace
			);
	
			$this -> data = ($exception instanceof BaseException)
				? $exception -> data
				: null;
		}
	
		$this -> output = array(
			"code" => $this -> code,
			"status" => $this -> status,
			"description" => $this -> description,
			"details" => $details,
			"caller" => "{$caller}()",
			"user" => class_exists(Session::class, true)
				? Session::$username
				: null,
			"data" => $this -> data,
			"hash" => $this -> hash,
			"runtime" => $RUNTIME -> stop(),
			"exception" => $exceptionData
		);

		return $this -> output;
	}

	public function process(): string {
		$this -> json(array_merge($this -> output(), $this -> json));
		return parent::process();
	}
}
