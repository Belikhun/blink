<?php

namespace Blink;

use Blink\DB\Exception\DatabaseNotUpgraded;
use Blink\Terra\Schema;
use Blink\Terra\Stack;

/**
 * Blink's schema, as a stack of layers raised in order.
 *
 * Every call here runs behind the same lock, so two requests noticing pending
 * work at once do not both try to raise it.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Terra {
	/**
	 * File the applied state hash is kept in, so the common path where
	 * nothing is pending costs one small read instead of a directory scan and
	 * a query.
	 */
	const STATE_FILE = "/caches/terra.state";

	protected static ?Stack $stack = null;

	public static function stack(): Stack {
		if (static::$stack === null)
			static::$stack = new Stack();

		return static::$stack;
	}

	/**
	 * Raise every pending layer.
	 *
	 * @param	int			$step	Stop after this many layers. Zero for all of them.
	 * @return	string[]	Identifiers raised.
	 */
	public static function apply(int $step = 0): array {
		return static::guarded(fn () => static::stack() -> apply($step));
	}

	/**
	 * Drop the most recent batches.
	 *
	 * @return	string[]	Identifiers dropped.
	 */
	public static function revert(int $step = 1): array {
		return static::guarded(fn () => static::stack() -> revert($step));
	}

	/**
	 * Drop every applied layer.
	 *
	 * @return	string[]	Identifiers dropped.
	 */
	public static function reset(): array {
		return static::guarded(fn () => static::stack() -> reset());
	}

	/**
	 * Drop everything and raise it again from nothing.
	 *
	 * Takes the lock once around both halves, so the schema is never left
	 * half dropped for another process to find.
	 *
	 * @return	string[]	Identifiers raised.
	 */
	public static function rebuild(): array {
		return static::guarded(function () {
			Schema::dropAll();
			static::forgetState();

			return static::stack() -> apply();
		});
	}

	/**
	 * @return	array<int, array{layer: string, path: ?string, applied: bool, batch: ?int, raised: ?int, missing: bool}>
	 */
	public static function status(): array {
		return static::stack() -> status();
	}

	/**
	 * @return	array<string, string>	identifier => absolute path
	 */
	public static function pending(): array {
		return static::stack() -> pending();
	}

	/**
	 * Raise pending layers during a web request, when the site asks for it.
	 *
	 * Hashes what is on disk and compares it against the last state that came
	 * out clean, so a site with nothing pending never touches the database
	 * here. Only when the hash moves does it go and look properly.
	 */
	public static function autoApply(): void {
		if (!\CONFIG::$TERRA_AUTO_APPLY)
			return;

		$state = static::state();

		if ($state === static::readState())
			return;

		$lock = static::stack() -> lock();

		// Another process is already raising the same layers. Waiting for it
		// is the whole point: the request behind it needs the schema too.
		if (!$lock -> block(\CONFIG::$TERRA_WAIT, 300)) {
			$pending = static::stack() -> pending();

			if (empty($pending)) {
				static::writeState($state);
				return;
			}

			throw new DatabaseNotUpgraded(count(static::stack() -> applied()), count(static::stack() -> discover()));
		}

		try {
			static::stack() -> apply();
			static::writeState($state);
		} finally {
			$lock -> release();
		}
	}

	/**
	 * Hash of every layer identifier on disk. Adding, removing or renaming a
	 * layer moves it; editing one does not, which is why a layer that has
	 * already run is never re-read.
	 */
	public static function state(): string {
		return sha1(implode("\n", array_keys(static::stack() -> discover())));
	}

	public static function forgetState(): void {
		$path = DATA_ROOT . static::STATE_FILE;

		if (file_exists($path))
			unlink($path);
	}

	protected static function readState(): ?string {
		$path = DATA_ROOT . static::STATE_FILE;

		return file_exists($path)
			? trim((string) @file_get_contents($path))
			: null;
	}

	protected static function writeState(string $state): void {
		@file_put_contents(DATA_ROOT . static::STATE_FILE, $state);
	}

	/**
	 * Run schema work while holding the Terra lock.
	 *
	 * @template	T
	 * @param		callable():T	$handler
	 * @return		T
	 */
	protected static function guarded(callable $handler) {
		$lock = static::stack() -> lock();

		if (!$lock -> block(\CONFIG::$TERRA_WAIT, 300, throw: true))
			return array();

		try {
			$result = $handler();
			static::forgetState();
			return $result;
		} finally {
			$lock -> release();
		}
	}
}
