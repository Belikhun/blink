<?php

namespace Blink\Metric;

/**
 * Query metric
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class QueryMetric extends MetricInstance {
	public string $mode;
	public string $table;
	public int $rows;

	/**
	 * Whether the statement raised rather than returned. A failed query still
	 * took time and still belongs in the timeline, so it is recorded and
	 * marked rather than dropped.
	 */
	public bool $errored = false;

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
		return sprintf("%5s %8s %16s %2d%s",
			$this -> timeFormat(),
			$this -> mode,
			$this -> table,
			$this -> rows,
			$this -> errored ? " [errored]" : "");
	}
}
