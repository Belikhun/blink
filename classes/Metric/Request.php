<?php

namespace Blink\Metric;

/**
 * Request.php
 * 
 * Request Metric
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Request extends Instance {
	public String $url;
	public String $method;
	public int $status;

	public function __construct(String $url, String $method) {
		$this -> url = $url;
		$this -> method = $method;
		$this -> time = -1;
		$this -> status = -1;
		$this -> start = microtime(true);
		\Blink\Metric::$requests[] = $this;
	}

	public function time(int $status) {
		$this -> status = $status;
		$this -> time = microtime(true);
	}

	public function __toString() {
		return sprintf("%5s %6s  %3d %s",
			$this -> timeFormat(),
			$this -> method,
			$this -> status,
			$this -> url);
	}
}
