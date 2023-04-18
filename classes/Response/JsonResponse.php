<?php
/**
 * JsonResponse.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Response;
use Blink\Response;

class JsonResponse extends Response {

	protected Array $json = Array();

	public function __construct(Array|Object $json = Array()) {
		$this -> json($json);
		$this -> header("Content-Type", "application/json; charset=utf-8");
	}

	public function json(Array|Object $json) {
		$this -> json = (Array) $json;
		return $this;
	}

	public function set(String $key, $value) {
		$this -> json[$key] = $value;
		return $this;
	}

	protected function process(): String {
		return json_encode($this -> json, JSON_PRETTY_PRINT);
	}
}
