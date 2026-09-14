<?php

namespace Blink;

use Blink\Exception\CodingError;
use Blink\Exception\LockAcquired;
use Blink\Lock\DatabaseStore;
use Blink\Lock\FileStore;
use Blink\Lock\Store;

/**
 * A named lock only one process can hold at a time.
 *
 * Every lock carries a time to live. A holder that crashes leaves its claim
 * behind, and the expiry is what lets work continue instead of waiting on a
 * process that is never coming back. That also means a holder which overruns
 * its TTL no longer owns the lock, so anything it does afterwards has to be
 * safe to happen twice.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Lock {
	const DEFAULT_TTL = 60;

	/**
	 * Stores already built, mapped by kind.
	 *
	 * @var array<string, Store>
	 */
	protected static array $stores = array();

	protected string $name;

	protected Store $store;

	/**
	 * Proof this instance is the current holder. Null while the lock is not
	 * held by us.
	 *
	 * @var ?string
	 */
	protected ?string $token = null;

	public function __construct(string $name, Store $store) {
		$this -> name = $name;
		$this -> store = $store;
	}

	/**
	 * A lock backed by the store named in `CONFIG::$LOCK_STORE`.
	 */
	public static function of(string $name): static {
		return new static($name, static::store(\CONFIG::$LOCK_STORE));
	}

	/**
	 * A lock backed by the database, which reaches every host sharing it.
	 */
	public static function database(string $name): static {
		return new static($name, static::store("database"));
	}

	/**
	 * A lock backed by the local filesystem. Used where the database is not
	 * available yet, which is the case while Terra is raising its own tables.
	 */
	public static function file(string $name): static {
		return new static($name, static::store("file"));
	}

	public static function store(string $kind): Store {
		if (isset(static::$stores[$kind]))
			return static::$stores[$kind];

		static::$stores[$kind] = match ($kind) {
			"database" => new DatabaseStore(),
			"file" => new FileStore(),
			default => throw new CodingError("Lock::store(): unknown lock store <code>{$kind}</code>.")
		};

		return static::$stores[$kind];
	}

	public function name(): string {
		return $this -> name;
	}

	/**
	 * Try to take the lock once.
	 *
	 * @param	int		$ttl	Seconds before the claim can be taken over.
	 * @param	bool	$throw	Raise {@see LockAcquired} rather than returning false.
	 * @return	bool
	 */
	public function acquire(int $ttl = self::DEFAULT_TTL, bool $throw = false): bool {
		if ($this -> held())
			return true;

		$token = bin2hex(random_bytes(16));

		if (!$this -> store -> acquire($this -> name, $token, $ttl, static::caller())) {
			if ($throw) {
				$holder = $this -> store -> holder($this -> name);
				throw new LockAcquired($this -> name, $holder["owner"] ?? null, $holder["expires"] ?? null);
			}

			return false;
		}

		$this -> token = $token;
		return true;
	}

	/**
	 * Wait for the lock, giving up after `$wait` seconds.
	 *
	 * @param	int		$wait	Seconds to keep trying for.
	 * @param	int		$ttl	Seconds the claim lasts once taken.
	 * @param	bool	$throw	Raise {@see LockAcquired} rather than returning false.
	 * @return	bool
	 */
	public function block(int $wait, int $ttl = self::DEFAULT_TTL, bool $throw = false): bool {
		$deadline = microtime(true) + max($wait, 0);

		do {
			if ($this -> acquire($ttl))
				return true;

			// A quarter of a second: short enough that a lock released early
			// is picked up promptly, long enough not to hammer the store.
			usleep(250000);
		} while (microtime(true) < $deadline);

		if ($throw) {
			$holder = $this -> store -> holder($this -> name);
			throw new LockAcquired($this -> name, $holder["owner"] ?? null, $holder["expires"] ?? null);
		}

		return false;
	}

	public function release(): bool {
		if ($this -> token === null)
			return false;

		$released = $this -> store -> release($this -> name, $this -> token);
		$this -> token = null;

		return $released;
	}

	/**
	 * Push the expiry out while still working. Returns false once the claim
	 * has already been taken over, which means the work is no longer protected.
	 */
	public function refresh(int $ttl = self::DEFAULT_TTL): bool {
		if ($this -> token === null)
			return false;

		return $this -> store -> refresh($this -> name, $this -> token, $ttl);
	}

	/**
	 * Whether this instance holds the lock right now.
	 */
	public function held(): bool {
		if ($this -> token === null)
			return false;

		$holder = $this -> store -> holder($this -> name);

		return !empty($holder) && $holder["token"] === $this -> token;
	}

	/**
	 * @return	?array{name: string, token: string, owner: string, acquired: int, expires: int}
	 */
	public function holder(): ?array {
		return $this -> store -> holder($this -> name);
	}

	/**
	 * Run a callable while holding the lock, releasing it however it ends.
	 *
	 * Returns null without running anything when the lock cannot be taken, so
	 * a caller that needs to tell "did not run" from "ran and returned null"
	 * should take the lock itself.
	 *
	 * @template	T
	 * @param		callable():T	$handler
	 * @return		?T
	 */
	public function run(callable $handler, int $ttl = self::DEFAULT_TTL, int $wait = 0) {
		$taken = ($wait > 0)
			? $this -> block($wait, $ttl)
			: $this -> acquire($ttl);

		if (!$taken)
			return null;

		try {
			return $handler($this);
		} finally {
			$this -> release();
		}
	}

	/**
	 * Where the lock was taken, as `Class -> method() at file:line`.
	 *
	 * Recorded on the claim itself so a stuck lock names the code that took
	 * it, which is the one question worth asking when one turns up.
	 */
	protected static function caller(): string {
		$frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
		$index = null;

		foreach ($frames as $position => $frame) {
			if (is_a($frame["class"] ?? "", self::class, true))
				continue;

			$index = $position;
			break;
		}

		if ($index === null || $index === 0)
			return "[unknown]";

		$frame = $frames[$index];
		$name = $frame["function"] ?? "[unknown]";

		if (!empty($frame["class"]))
			$name = $frame["class"] . (($frame["type"] ?? "::") === "->" ? " -> " : "::") . $name;

		// A frame's file and line say where its function was called from, not
		// where it lives. The call into Lock is one frame down, and that is
		// the line worth recording.
		$origin = $frames[$index - 1];

		$location = !empty($origin["file"])
			? " at " . getRelativePath($origin["file"]) . ":" . ($origin["line"] ?? 0)
			: "";

		return "{$name}(){$location}";
	}
}
