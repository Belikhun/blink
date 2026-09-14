<?php

namespace Blink\Metric;

use Blink\Metric;

/**
 * A named stretch of the request.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TimingMetric extends MetricInstance {
	public string $name;

	public function __construct(string $name, callable $handler = null) {
		parent::__construct();

		$this -> name = $name;
		Metric::$timings[] = $this;

		if (!empty($handler)) {
			$handler();
			$this -> time();
		}
	}

	public function time(int $opt = 0): static {
		$this -> time = microtime(true);
		return $this;
	}

	public function save(): ?array {
		if ($this -> time <= 0)
			return null;

		return array(
			"name" => $this -> name,
			"delta_start" => $this -> deltaStart,
			"duration" => $this -> getTime()
		);
	}

	public function __toString(): string {
		return sprintf("%5s  %s",
			$this -> timeFormat(),
			$this -> name);
	}

	public function __serialize(): array {
		return array(
			"name" => $this -> name,
			"start" => $this -> start,
			"deltaStart" => $this -> deltaStart,
			"time" => $this -> time
		);
	}

	public function __unserialize(array $data): void {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}
