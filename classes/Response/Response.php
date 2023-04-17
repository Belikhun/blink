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
use Blink\Exception\HeaderSent;

class Response {
	/**
	 * HTTP response status codes indicate whether a specific HTTP request has been successfully completed.
	 * Responses are grouped in five classes:
	 * 
	 * 1. Informational responses (`100` – `199`)
	 * 2. Successful responses (`200` – `299`)
	 * 3. Redirection messages (`300` – `399`)
	 * 4. Client error responses (`400` – `499`)
	 * 5. Server error responses (`500` – `599`)
	 * 
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
	 */
	protected int $status = 200;

	/**
	 * List of headers set for this response.
	 * Key will be the header name, while the value can either
	 * be a string or an array. An array indicate this header has
	 * multiple values, while a string indicate a single value header.
	 * @var string[]|array
	 */
	protected Array $headers = Array();

	/**
	 * The cookie bag 🍪.
	 * Key represent the cookie name, while the value is the cookie itself.
	 * @var \Blink\Cookie[]
	 */
	protected Array $cookies = Array();

	protected String $body;

	public function __construct(String $body = "") {
		$this -> body($body);
		$this -> header("Content-Type", "text/html; charset=utf-8");
	}

	/**
	 * Set HTTP response code for this response.
	 * 
	 * @param	int		$status		HTTP Response Code.
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
	 */
	public function status(int $status) {
		$this -> status = $status;
		return $this;
	}

	/**
	 * Add a cookie 🍪 to this response.
	 * @param	Cookie	$cookie
	 */
	public function cookie(Cookie $cookie) {
		$this -> cookies[$cookie -> name] = $cookie;
		return $this;
	}

	/**
	 * Add multiple cookie 🍪 to this response.
	 * @param	Cookie[]	$cookies
	 */
	public function cookies(Array $cookies) {
		foreach ($cookies as $cookie)
			$this -> cookie($cookie);
		
		return $this;
	}

	public function addCookie(String $name, String $value) {
		$cookie = new Cookie($name, $value);
		$this -> cookie($cookie);
		return $cookie;
	}

	protected function normalizeHeaderName(String $name) {
		$name = str_replace("_", "-", trim($name));
		return ucwords($name, "-");
	}

	public function header(String $name, String|Array $value, bool $overwrite = true) {
		$name = $this -> normalizeHeaderName($name);

		if (is_array($value))
			$value = implode("; ", $value);
		
		if ($overwrite) {
			if (empty($value))
				unset($this -> headers[$name]);
			else
				$this -> headers[$name] = $value;
			
			return $this;
		}

		if (empty($value))
			return $this;

		if (!isset($this -> headers[$name]))
			$this -> headers[$name] = Array();

		if (!is_array($this -> headers[$name])) {
			$header = Array();
			if (!empty($this -> headers[$name]))
				$header[] = $this -> headers[$name];
			
			$header[] = $value;
			$this -> headers[$name] = $header;
		} else {
			$this -> headers[$name][] = $value;
		}

		return $this;
	}

	public function removeHeader(String $name) {
		$name = $this -> normalizeHeaderName($name);

		if (isset($this -> headers[$name]))
			unset($this -> headers[$name]);

		return $this;
	}

	public function headers(Array $headers, bool $overwrite = true) {
		foreach ($headers as $name => $value)
			$this -> header($name, $value, $overwrite);

		return $this;
	}

	public function headerSet(String $name) {
		return isset($this -> headers[$name]);
	}

	public function body(String $body) {
		$this -> body = $body;
		return $this;
	}

	/**
	 * Set expire headers for this response.
	 * @param	int		$time	Expire time, in seconds.
	 */
	public function expire(int $time) {
		$this
			-> header("Cache-Control", "public, max-age=$time")
			-> header("Expires", gmdate("D, d M Y H:i:s \G\M\T", time() + $time))
			-> removeHeader("Pragma");
		
		return $this;
	}

	protected function process(): String {
		return $this -> body;
	}

	/**
	 * Reponse to the request. This will set necessary status/headers/cookies before
	 * calling {@link process()} to get the response body.
	 * @return	string	Return outputted body.
	 */
	public function serve(): ?String {
		$body = $this -> process();
		
		http_response_code($this -> status);

		if (headers_sent($hfile, $hline))
			throw new HeaderSent($hfile, $hline);

		// Process our cookie bag.
		foreach ($this -> cookies as $name => $cookie)
			$this -> header("Set-Cookie", $cookie -> build(), false);

		foreach ($this -> headers as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $item) {
					if (empty($item))
						continue;

					header("{$key}: {$item}", true);
				}

				continue;
			}

			header("{$key}: {$value}", true);
		}

		return $body;
	}
}
