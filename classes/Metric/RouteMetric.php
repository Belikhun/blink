<?php

namespace Blink\Metric;

use Blink\Http\Request;
use Blink\Http\Response;
use Blink\Http\Router\Route;

/**
 * Route metric
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouteMetric extends MetricInstance {
	public Route $route;

	public int $status = -1;

	public ?Request $request = null;

	public ?Response $response = null;

	public function __construct(Route $route) {
		$this -> route = $route;
		$this -> start = microtime(true);
		\Blink\Metric::$routes[] = $this;
	}

	public function time(
		int $status,
		Request $request = null,
		Response $response = null
	): static {
		$this -> status = $status;
		$this -> request = $request;
		$this -> response = $response;
		$this -> time = microtime(true);
		return $this;
	}

	public function __toString(): string {
		return sprintf("%5s %3d %s",
			$this -> timeFormat(),
			$this -> status,
			(string) $this -> route
		);
	}
}
