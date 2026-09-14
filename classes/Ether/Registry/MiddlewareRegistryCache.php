<?php

namespace Blink\Ether\Registry;

use Blink\Cache;
use Blink\Essence;

/**
 * Persistence for the Ether middleware registry.
 *
 * The registry is rebuilt from scratch by every module's `attach()`, so the
 * cache saves the contract inspection rather than the registration: what it
 * holds back is a `class_exists()` plus a handful of `is_subclass_of()` calls
 * per middleware, on every request.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class MiddlewareRegistryCache {
	protected const CACHE_ID = "ether.registry";

	protected ?string $lastKnownVersion = null;

	protected ?array $lastKnownSnapshot = null;

	public static function make(): static {
		return Essence::make(static::class);
	}

	/**
	 * Version the stored snapshot is keyed on. A snapshot written for another
	 * blink, application or module list describes middleware that may no
	 * longer exist.
	 */
	public function getVersion(): string {
		return \Blink::cacheVersion();
	}

	public function load(string $version): ?array {
		$cache = Cache::instance(static::CACHE_ID);

		if (!$cache -> fetch() || !$cache -> validate())
			return null;

		$stored = $cache -> content();

		if (!is_array($stored) || ($stored["version"] ?? null) !== $version) {
			$this -> lastKnownVersion = null;
			$this -> lastKnownSnapshot = null;
			return null;
		}

		$snapshot = $stored["snapshot"] ?? null;

		if (!is_array($snapshot)) {
			$this -> lastKnownVersion = null;
			$this -> lastKnownSnapshot = null;
			return null;
		}

		$this -> lastKnownVersion = $version;
		$this -> lastKnownSnapshot = $snapshot;

		return $snapshot;
	}

	public function store(string $version, array $definitions, array $aliases, array $groups): void {
		$snapshot = array(
			"definitions" => $definitions,
			"aliases" => $aliases,
			"groups" => $groups
		);

		// Registration runs on every request and almost never changes anything,
		// so the comparison is what keeps the common path from writing a file.
		if ($this -> lastKnownVersion === $version && $this -> lastKnownSnapshot === $snapshot)
			return;

		if ($this -> load($version) === $snapshot)
			return;

		Cache::instance(static::CACHE_ID)
			-> setContent(array( "version" => $version, "snapshot" => $snapshot ))
			-> save();

		$this -> lastKnownVersion = $version;
		$this -> lastKnownSnapshot = $snapshot;
	}

	public function purge(): void {
		$this -> lastKnownVersion = null;
		$this -> lastKnownSnapshot = null;
		Cache::remove(static::CACHE_ID);
	}
}
