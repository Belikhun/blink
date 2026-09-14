<?php

namespace Blink;

/**
 * Metric base class that store all metrics recorded during this request.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Metric {
	/** @var Metric\RequestMetric[] */
	public static $requests = array();

	/** @var Metric\RouteMetric[] */
	public static $routes = array();

	/** @var Metric\QueryMetric[] */
	public static $queries = array();

	/** @var Metric\FileMetric[] */
	public static $files = array();

	/** @var Metric\TimingMetric[] */
	public static $timings = array();

	/**
	 * Drop everything recorded so far.
	 *
	 * A web request ends and takes its metrics with it. A long-running process
	 * does not, and would otherwise accumulate every query it has ever run,
	 * so it clears them between units of work.
	 */
	public static function reset(): void {
		static::$requests = array();
		static::$routes = array();
		static::$queries = array();
		static::$files = array();
		static::$timings = array();
	}
}
