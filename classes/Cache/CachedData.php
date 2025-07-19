<?php

namespace Blink\Cache;

use Blink\Cache;

/**
 * Cache data object.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CachedData {
	public const CONTENT_EMPTY = "[cached_data:empty]";

	public string $id;

	public int $age = Cache::NO_EXPIRE;

	public int $time = 0;

	public mixed $content = CachedData::CONTENT_EMPTY;

	public function __serialize() {
		return array(
			"id" => $this -> id,
			"age" => $this -> age,
			"time" => $this -> time,
			"content" => $this -> content
		);
	}

	public function __unserialize(array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}
