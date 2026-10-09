<?php

namespace Blink;

/**
 * Everything measured during this request, by kind.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
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

	/** @var Metric\ErrorMetric[] */
	public static $errors = array();

	const REQUESTS = "requests";
	const ROUTES = "routes";
	const QUERIES = "queries";
	const FILES = "files";
	const TIMINGS = "timings";
	const ERRORS = "errors";

	/**
	 * Every kind, in the order the buckets above are declared.
	 */
	const KINDS = array(
		self::REQUESTS,
		self::ROUTES,
		self::QUERIES,
		self::FILES,
		self::TIMINGS,
		self::ERRORS
	);

	/**
	 * Whether a newly built metric is kept at all.
	 *
	 * The instance is still built and still usable by its caller; it is just
	 * not appended to the buckets above, so it is garbage the moment the
	 * caller drops it. The buckets are per process and unbounded, which is
	 * fine for a web request and ruinous for a long-running one: a runner
	 * issuing hundreds of thousands of statements holds a `QueryMetric` for
	 * every one of them until it exits.
	 */
	public static bool $capturing = true;

	/**
	 * Per-kind switches, layered under `$capturing`: a kind that is off here
	 * is never appended even while capturing is on. Lets a process keep
	 * request metrics but drop, say, query metrics, the kind that dominates
	 * both the memory held and the rows written at shutdown.
	 *
	 * @var array<string, bool>
	 */
	public static array $enabled = array(
		self::REQUESTS => true,
		self::ROUTES => true,
		self::QUERIES => true,
		self::FILES => true,
		self::TIMINGS => true,
		self::ERRORS => true
	);

	/**
	 * Whether a newly built metric of this kind is kept.
	 *
	 * @param	string	$kind	One of {@see Metric::KINDS}.
	 */
	public static function captures(string $kind): bool {
		return static::$capturing && (static::$enabled[$kind] ?? true);
	}

	/**
	 * Stop keeping the given kinds and drop what they already hold, so what
	 * was recorded before the decision was made (blink's own bootstrap
	 * timings and queries) is not written anyway.
	 *
	 * @param	string	...$kinds	Kinds from {@see Metric::KINDS}.
	 */
	public static function disable(string ...$kinds): void {
		foreach ($kinds as $kind) {
			static::$enabled[$kind] = false;
			static::discard($kind);
		}
	}

	/**
	 * Resume keeping the given kinds.
	 *
	 * @param	string	...$kinds	Kinds from {@see Metric::KINDS}.
	 */
	public static function enable(string ...$kinds): void {
		foreach ($kinds as $kind)
			static::$enabled[$kind] = true;
	}

	/**
	 * Drop everything recorded so far, or only one kind of it.
	 *
	 * A web request ends and takes its metrics with it. A long-running process
	 * does not, and would otherwise accumulate every query it has ever run,
	 * so it clears them between units of work.
	 *
	 * @param	?string	$kind	One of {@see Metric::KINDS}, or every kind when omitted.
	 */
	public static function discard(?string $kind = null): void {
		foreach (static::KINDS as $bucket) {
			if ($kind !== null && $kind !== $bucket)
				continue;

			static::${$bucket} = array();
		}
	}
}
