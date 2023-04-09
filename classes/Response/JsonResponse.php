<?php
/**
 * JsonResponse.php
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
use Blink\Response;

class JsonResponse extends Response {

	protected Array $body;

	public function __construct(Array|Object $body) {
		$this -> body($body);
		$this -> header("Content-Type", "application/json; charset=utf-8");
	}

	public function body(Array|Object $body) {
		$this -> body = (Array) $body;
		return $this;
	}

	protected function process(): String {
		return json_encode($this -> body, JSON_PRETTY_PRINT);
	}
}
