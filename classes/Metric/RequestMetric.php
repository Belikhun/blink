<?php

namespace Blink\Metric;

/**
 * An outgoing HTTP request made during this one.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RequestMetric extends MetricInstance {
	public string $url;

	/**
	 * The url without its query string, which is what repeats.
	 */
	public string $endpoint;

	public string $hash;

	public string $method;

	public int $status = -1;

	public function __construct(string $url, string $method) {
		parent::__construct();

		$this -> url = $url;
		$this -> endpoint = strtok($url, "?") ?: $url;
		$this -> hash = substr(md5($this -> endpoint), 0, 12);
		$this -> method = strtoupper($method);

		if (\Blink\Metric::captures(\Blink\Metric::REQUESTS))
			\Blink\Metric::$requests[] = $this;
	}

	public function time(int $status): static {
		$this -> status = $status;
		$this -> time = microtime(true);
		return $this;
	}

	public function save(): ?array {
		return array(
			"hash" => $this -> hash,
			"endpoint" => $this -> endpoint,
			"url" => $this -> url,
			"method" => $this -> method,
			"status" => $this -> status,
			"delta_start" => $this -> deltaStart,
			"duration" => $this -> getTime()
		);
	}

	public function __toString(): string {
		return sprintf("%5s %6s  %3d %s",
			$this -> timeFormat(),
			$this -> method,
			$this -> status,
			$this -> url);
	}
}
