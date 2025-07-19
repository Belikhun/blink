<?php

namespace Blink\Http\Response;

use Blink\Http\Response;
use JsonSerializable;

/**
 * Return a json response of an object
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class JsonResponse extends Response {

	protected array $json = array();

	public function __construct(array|object $json = array()) {
		$this -> json($json);
		$this -> header("Content-Type", "application/json; charset=utf-8");
	}

	public function json(array|object $json) {
		if ($json instanceof JsonSerializable) {
			$this -> json = $json -> jsonSerialize();
			return $this;
		}

		$this -> json = (array) $json;
		return $this;
	}

	public function set(string $key, $value) {
		$this -> json[$key] = $value;
		return $this;
	}

	protected function process(): string {
		return json_encode($this -> json, JSON_PRETTY_PRINT);
	}
}
