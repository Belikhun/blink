<?php
/**
 * Response.php
 * 
 * Base class for normal text response.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;

class Response {
	protected int $status = 200;

	protected Array $headers = Array();

	protected $body;

	public function __construct(String $body = "") {
		$this -> body($body);
	}

	public function status(int $status) {
		$this -> status = $status;
		return $this;
	}

	public function header(String $name, String $value) {
		$this -> headers[$name] = $value;
		return $this;
	}

	public function headers(Array $headers) {
		foreach ($headers as $name => $value)
			$this -> header($name, $value);

		return $this;
	}

	public function body(String $body) {
		$this -> body = $body;
		return $this;
	}

	protected function process(): String {
		return $this -> body;
	}

	/**
	 * Reponse to the request. This will set necessary status/headers/cookies before
	 * calling {@link process()} to get the response body.
	 */
	public function serve() {
		http_response_code($this -> status);

		foreach ($this -> headers as $key => $value)
			header("{$key}: {$value}");

		echo $this -> process();
	}
}
