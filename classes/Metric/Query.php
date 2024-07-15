<?php

namespace Blink\Metric;

/**
 * Query.php
 * 
 * Query Metric
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Query extends Instance {
	public string $mode;
	public string $table;
	public int $rows;

	public function __construct(string $mode, string $table) {
		$this -> mode = $mode;
		$this -> table = $table;
		$this -> time = -1;
		$this -> rows = -1;
		$this -> start = microtime(true);
		\Blink\Metric::$queries[] = $this;
	}

	public function time(int $rows) {
		$this -> rows = $rows;
		$this -> time = microtime(true);
	}

	public function __toString(): string {
		return sprintf("%5s %8s %16s %2d",
			$this -> timeFormat(),
			$this -> mode,
			$this -> table,
			$this -> rows);
	}
}