<?php

namespace Blink\Lightrail\Registry;

use Blink\Cache;
use Blink\Essence;

/**
 * Persistence for the listener registry.
 *
 * What it saves is the discovery: reflecting over every observer class to read
 * its attributes, on every request. The registry itself is cheap to hold; the
 * reflection is not.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RegistryCache {
	protected const CACHE_ID = "lightrail.registry";

	protected ?string $lastKnownVersion = null;

	protected ?array $lastKnownSnapshot = null;

	public static function make(): static {
		return Essence::make(static::class);
	}

	protected static ?string $observersDigest = null;

	/**
	 * A snapshot written for another blink, application, module list or set
	 * of observer files describes handlers that may no longer exist. The file
	 * times are what let an added or edited observer take effect without a
	 * cache clear.
	 */
	public function getVersion(): string {
		return \Blink::cacheVersion() . ":" . static::observersDigest();
	}

	/**
	 * One digest for the observer classes every module declares and when their
	 * files last changed. Computed once per process, so a registry stored late
	 * in a request agrees with the one loaded early.
	 */
	protected static function observersDigest(): string {
		if (static::$observersDigest !== null)
			return static::$observersDigest;

		$parts = array();

		foreach (\Blink::modules() as $module) {
			foreach ($module -> observers() as $observer) {
				$stamp = 0;

				try {
					$file = (new \ReflectionClass($observer)) -> getFileName();
					$stamp = ($file !== false) ? (int) @filemtime($file) : 0;
				} catch (\ReflectionException $e) {
					// No class, no file to stamp. The build reports it.
				}

				$parts[] = "{$observer}@{$stamp}";
			}
		}

		sort($parts);
		return static::$observersDigest = sha1(implode("|", $parts));
	}

	/**
	 * @return	?array{all: array, byEvent: array}
	 */
	public function load(string $version): ?array {
		$cache = Cache::instance(static::CACHE_ID);

		if (!$cache -> fetch() || !$cache -> validate())
			return null;

		$stored = $cache -> content();

		if (!is_array($stored) || ($stored["version"] ?? null) !== $version) {
			$this -> forget();
			return null;
		}

		$snapshot = $stored["snapshot"] ?? null;

		if (!is_array($snapshot) || !isset($snapshot["all"], $snapshot["byEvent"])) {
			$this -> forget();
			return null;
		}

		$this -> lastKnownVersion = $version;
		$this -> lastKnownSnapshot = $snapshot;

		return $snapshot;
	}

	public function store(string $version, array $all, array $byEvent): void {
		$snapshot = array( "all" => $all, "byEvent" => $byEvent );

		// The per-event memo grows as events are dispatched, so this is called
		// often with nothing new to say. The comparison is what keeps the
		// common path from writing a file.
		if ($this -> lastKnownVersion === $version && $this -> lastKnownSnapshot === $snapshot)
			return;

		Cache::instance(static::CACHE_ID)
			-> setContent(array( "version" => $version, "snapshot" => $snapshot ))
			-> save();

		$this -> lastKnownVersion = $version;
		$this -> lastKnownSnapshot = $snapshot;
	}

	public function purge(): void {
		$this -> forget();
		Cache::remove(static::CACHE_ID);
	}

	protected function forget(): void {
		$this -> lastKnownVersion = null;
		$this -> lastKnownSnapshot = null;
	}
}
