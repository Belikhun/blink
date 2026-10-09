<?php

namespace Blink;

use Blink\DB\Database;
use Blink\DB\Exception\DatabaseNotUpgraded;
use Blink\Terra\Schema;
use Blink\Terra\Stack;

/**
 * Blink's schema, as a stack of layers raised in order.
 *
 * Every call here runs behind the same lock, so two requests noticing pending
 * work at once do not both try to raise it. Each takes an optional instance
 * name to work on that instance's layers alone; left out, they work across
 * every instance the site has.
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
	 * @param	int			$step		Stop after this many layers. Zero for all of them.
	 * @param	?string		$database	Only layers targeting this instance.
	 * @return	string[]	Identifiers raised.
	 */
	public static function apply(int $step = 0, ?string $database = null): array {
		return static::guarded(fn () => static::stack() -> apply($step, $database));
	}

	/**
	 * Drop the most recent batches.
	 *
	 * @param	int			$step		How many batches to drop.
	 * @param	?string		$database	Only this instance's ledger.
	 * @return	string[]	Identifiers dropped.
	 */
	public static function revert(int $step = 1, ?string $database = null): array {
		return static::guarded(fn () => static::stack() -> revert($step, $database));
	}

	/**
	 * Drop every applied layer.
	 *
	 * @param	?string		$database	Only this instance's ledger.
	 * @return	string[]	Identifiers dropped.
	 */
	public static function reset(?string $database = null): array {
		return static::guarded(fn () => static::stack() -> reset($database));
	}

	/**
	 * Drop everything and raise it again from nothing.
	 *
	 * Takes the lock once around both halves, so the schema is never left
	 * half dropped for another process to find.
	 *
	 * @param	?string		$database	Only this instance: its tables and its layers.
	 * @return	string[]	Identifiers raised.
	 */
	public static function rebuild(?string $database = null): array {
		return static::guarded(function () use ($database) {
			foreach (static::stack() -> databases() as $name => $instance) {
				if ($database === null || $name === $database)
					Schema::on($instance, fn () => Schema::dropAll());
			}

			static::forgetState();

			return static::stack() -> apply(database: $database);
		});
	}

	/**
	 * Remove a layer's ledger rows without touching the schema.
	 *
	 * @param	?string		$database	Only this instance's ledger.
	 * @return	string[]	Instances a row was removed from.
	 */
	public static function forget(string $layer, ?string $database = null): array {
		return static::guarded(fn () => static::stack() -> forgetRows($layer, $database));
	}

	/**
	 * @return	array<int, array{layer: string, path: ?string, database: string, state: string, applied: bool, batch: ?int, raised: ?int, missing: bool, note: ?string}>
	 */
	public static function status(?string $database = null): array {
		return static::stack() -> status($database);
	}

	/**
	 * @return	array<string, string>	identifier => absolute path
	 */
	public static function pending(?string $database = null): array {
		return static::stack() -> pending($database);
	}

	/**
	 * Layers held back by their target instance.
	 *
	 * @return	array<string, array>	identifier => status row
	 */
	public static function blocked(?string $database = null): array {
		return static::stack() -> blocked($database);
	}

	/**
	 * Instances that could not be connected, with the reason.
	 *
	 * @return	array<string, string>
	 */
	public static function unreachable(): array {
		return static::stack() -> unreachable();
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

			// A layer waiting for an instance that is not registered cannot
			// be helped until the config changes, which moves the hash. One
			// whose instance is merely down can: the state is left unwritten
			// so the next request tries again.
			if (empty(static::stack() -> unreachable()))
				static::writeState($state);
		} finally {
			$lock -> release();
		}
	}

	/**
	 * Hash of every layer identifier on disk and every instance name. Adding,
	 * removing or renaming a layer moves it, and so does registering an
	 * instance, which is what lets a waiting layer run; editing a layer does
	 * not, which is why a layer that has already run is never re-read.
	 */
	public static function state(): string {
		return sha1(
			implode("\n", array_keys(static::stack() -> discover()))
			. "\n#" . implode(",", Database::names())
		);
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
