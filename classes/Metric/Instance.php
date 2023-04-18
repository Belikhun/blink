<?php
/**
 * Metric.php
 * 
 * Metric instance
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Metric;

abstract class Instance {
	public float $time;
	protected float $start;

	abstract public function time(int $stat);

	public function getTime() {
		return ($this -> time > 0)
			? $this -> time - $this -> start
			: -1;
	}

	protected function timeFormat(): String {
		return ($this -> getTime() > 0)
			? sprintf("%01.2fs", $this -> getTime())
			: " fail";
	}

	abstract public function __toString(): String;
}