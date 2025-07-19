<?php

namespace Blink\Metric;

/**
 * Metric instance base class
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class MetricInstance {
	public float $time = -1;
	protected float $start;

	abstract public function time(int $stat);

	public function getTime() {
		return ($this -> time > 0)
			? $this -> time - $this -> start
			: -1;
	}

	protected function timeFormat(): string {
		return ($this -> getTime() > 0)
			? sprintf("%01.2fs", $this -> getTime())
			: " fail";
	}

	abstract public function __toString(): string;
}
