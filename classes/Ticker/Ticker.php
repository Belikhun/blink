<?php

namespace Blink;

use Blink\Ticker\Exception\StoreNotFound;
use Blink\Ticker\Orbit;
use Blink\Ticker\Store\DatabaseStore;
use Blink\Ticker\Store\DirectStore;
use Blink\Ticker\Store\Store;
use Blink\Ticker\Task;

/**
 * Work that happens later, and work that happens again.
 *
 * Queue something for a runner to pick up:
 *
 * ```php
 * Ticker::queue(new SendInvoice($invoice -> id));
 * Ticker::queue(new Reminder($id), delay: 3600);
 * ```
 *
 * A runner drains the queue (`blink ticker:run`), and a once-a-minute tick
 * from the system cron starts whatever is due (`blink ticker:tick`).
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Ticker {
	const STORES = array( "database", "direct" );

	protected static ?Store $store = null;

	protected static ?Orbit $orbit = null;

	/**
	 * Put a task on its queue.
	 *
	 * @param	Task	$task
	 * @param	int		$delay	Seconds before a runner may pick it up.
	 * @param	?string	$queue	Overrides the task's own queue.
	 * @return	?int	The queued id, or null when the store ran it inline.
	 */
	public static function queue(Task $task, int $delay = 0, string $queue = null): ?int {
		return static::store() -> push($task, static::queueName($task, $queue), $delay);
	}

	/**
	 * Put a task on its queue unless one carrying the same key is waiting.
	 *
	 * This is how a burst of identical requests turns into one run. The task
	 * has to answer {@see Task::key()} with something; one that does not is
	 * queued normally.
	 *
	 * @return	?int	The queued id, or null when one was already waiting.
	 */
	public static function queueUnique(Task $task, int $delay = 0, string $queue = null): ?int {
		return static::store() -> pushUnique($task, static::queueName($task, $queue), $delay);
	}

	/**
	 * Make a waiting task eligible sooner than it was, by key.
	 *
	 * For work that was scheduled to happen in a while but now needs to happen
	 * at once, such as a batch that filled up before its window closed.
	 */
	public static function pullForward(string $key, int $when = null): bool {
		return static::store() -> pullForward($key, $when ?? time());
	}

	/**
	 * Drop every waiting task carrying a key.
	 */
	public static function forget(string $key): int {
		return static::store() -> forget($key);
	}

	/**
	 * How many tasks are waiting.
	 */
	public static function size(string $queue = null): int {
		return static::store() -> size($queue);
	}

	public static function store(): Store {
		if (static::$store !== null)
			return static::$store;

		$name = \CONFIG::$TICKER_STORE;

		static::$store = match ($name) {
			"database" => new DatabaseStore(),
			"direct" => new DirectStore(),
			default => throw new StoreNotFound($name, static::STORES)
		};

		return static::$store;
	}

	/**
	 * Swap the store, for a command that has to reach one other than the
	 * configured default.
	 */
	public static function useStore(Store $store): void {
		static::$store = $store;
	}

	/**
	 * Everything every module schedules, collected once.
	 */
	public static function orbit(): Orbit {
		if (static::$orbit !== null)
			return static::$orbit;

		static::$orbit = new Orbit();

		foreach (\Blink::modules() as $module)
			$module -> orbits(static::$orbit);

		return static::$orbit;
	}

	protected static function queueName(Task $task, string $queue = null): string {
		return $queue
			?? $task -> queue
			?? \CONFIG::$TICKER_QUEUE;
	}
}
