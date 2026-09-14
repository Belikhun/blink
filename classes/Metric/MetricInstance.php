<?php

namespace Blink\Metric;

/**
 * One measured thing: a query, a file read, a stretch of the request.
 *
 * Every instance records when it started, both as a clock time and as an
 * offset from the start of the request, so a timeline can place it without
 * knowing when the request began.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class MetricInstance {
	public float $time = -1;

	public float $start;

	/**
	 * Seconds from the start of the request to the start of this metric.
	 */
	public float $deltaStart = 0;

	public function __construct() {
		global $RUNTIME;

		$this -> start = microtime(true);
		$this -> deltaStart = !empty($RUNTIME) ? $RUNTIME -> stop() : 0;
	}

	/**
	 * Mark the end, with whatever count describes the outcome: rows, bytes,
	 * a status.
	 */
	abstract public function time(int $stat): static;

	public function getTime(): float {
		return ($this -> time > 0)
			? $this -> time - $this -> start
			: -1;
	}

	protected function timeFormat(): string {
		return ($this -> getTime() > 0)
			? sprintf("%01.2fs", $this -> getTime())
			: " fail";
	}

	/**
	 * The metric as a flat row for storage, or null when there is nothing
	 * worth keeping.
	 */
	abstract public function save(): ?array;

	abstract public function __toString(): string;
}
