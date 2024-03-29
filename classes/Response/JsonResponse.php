<?php

namespace Blink\Response;

use Blink\Response;
use JsonSerializable;

/**
 * JsonResponse.php
 * 
 * Return a json response of an object.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class JsonResponse extends Response {

	protected Array $json = Array();

	public function __construct(Array|Object $json = Array()) {
		$this -> json($json);
		$this -> header("Content-Type", "application/json; charset=utf-8");
	}

	public function json(Array|Object $json) {
		if ($json instanceof JsonSerializable) {
			$this -> json = $json -> jsonSerialize();
			return $this;
		}

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
