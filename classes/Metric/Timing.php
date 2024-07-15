<?php

namespace Blink\Metric;

use Blink\Metric;

/**
 * Timing.php
 * 
 * Timing Metric
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Timing extends Instance {
	public string $name;
	public float $start;

	public function __construct(string $name, Callable $handler = null) {
		$this -> name = $name;
		$this -> start = microtime(true);
		Metric::$timings[] = $this;

		if (!empty($handler)) {
			$handler();
			$this -> time();
		}
	}

	public function time(int $opt = 0) {
		$this -> time = microtime(true);
	}

	public function __toString() {
		return sprintf("%5s  %s",
			$this -> timeFormat(),
			$this -> name);
	}

	public function __serialize() {
		return array(
			"name" => $this -> name,
			"start" => $this -> start,
			"time" => $this -> time
		);
	}

	public function __unserialize(array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}
