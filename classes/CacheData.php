<?php

namespace Blink\Cache;
use Blink\Cache;

/**
 * CacheData.php
 * 
 * Cache Data object.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Data {
	public String $id;

	public int $age = Cache::NO_EXPIRE;

	public int $time = 0;

	public mixed $content = null;

	public function __serialize() {
		return Array(
			"id" => $this -> id,
			"age" => $this -> age,
			"time" => $this -> time,
			"content" => $this -> content
		);
	}

	public function __unserialize(Array $data) {
		$this -> id = $data["id"];
		$this -> age = $data["age"];
		$this -> time = $data["time"];
		$this -> content = $data["content"];
	}
}