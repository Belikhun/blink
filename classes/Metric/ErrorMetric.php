<?php

namespace Blink\Metric;

/**
 * An error report filed during this request.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ErrorMetric extends MetricInstance {
	public string $id;

	/**
	 * Short digest of the error class and where it came from, so repeats of
	 * the same failure can be grouped.
	 */
	public string $hash;

	public string $error;

	public string $description;

	public ?string $details = null;

	public ?string $source = null;

	public function __construct(
		string $id,
		string $error,
		string $description,
		?string $details = null,
		?string $source = null
	) {
		parent::__construct();

		$this -> id = $id;
		$this -> error = $error;
		$this -> description = $description;
		$this -> details = $details;
		$this -> source = $source;
		$this -> hash = substr(md5($error . $source), 0, 7);
		$this -> time = microtime(true);

		if (\Blink\Metric::captures(\Blink\Metric::ERRORS))
			\Blink\Metric::$errors[] = $this;
	}

	public function time(int $stat = 0): static {
		$this -> time = microtime(true);
		return $this;
	}

	public function save(): array {
		return array(
			"error_id" => $this -> id,
			"hash" => $this -> hash,
			"error" => $this -> error,
			"description" => $this -> description,
			"details" => $this -> details,
			"source" => $this -> source
		);
	}

	public function __toString(): string {
		return sprintf("%8s %s  %s",
			$this -> id,
			$this -> error,
			$this -> source);
	}
}
