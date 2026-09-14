<?php

namespace Blink\Ticker\Store;

use Blink\Ticker\Task;

/**
 * Where queued tasks are kept.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class Store {
	/**
	 * Put a task on a queue.
	 *
	 * @param	Task	$task
	 * @param	string	$queue
	 * @param	int		$delay	Seconds to wait before it becomes eligible.
	 * @return	?int	The queued id, or null when the store ran it instead.
	 */
	abstract public function push(Task $task, string $queue, int $delay = 0): ?int;

	/**
	 * Put a task on a queue unless one carrying the same key is already
	 * waiting.
	 *
	 * @return	?int	The queued id, or null when one was already there.
	 */
	abstract public function pushUnique(Task $task, string $queue, int $delay = 0): ?int;

	/**
	 * Take ownership of the next eligible task.
	 *
	 * @param	string	$queue
	 * @param	int		$ttl	Seconds the claim holds before another runner
	 * 							may take over.
	 * @return	?QueuedTask		Null when there is nothing to take.
	 */
	abstract public function claim(string $queue, int $ttl): ?QueuedTask;

	/**
	 * Put a claimed task back on the queue, to be tried again later.
	 */
	abstract public function release(QueuedTask $queued, int $delay = 0): bool;

	/**
	 * Take a finished task off the queue.
	 */
	abstract public function delete(QueuedTask $queued): bool;

	/**
	 * Move a task that has run out of attempts onto the failure list.
	 */
	abstract public function fail(QueuedTask $queued, \Throwable $e, string $reportId = null): void;

	/**
	 * How many tasks are waiting.
	 */
	abstract public function size(string $queue = null): int;

	/**
	 * Drop every waiting task carrying this key.
	 *
	 * @return	int		How many were dropped.
	 */
	abstract public function forget(string $key): int;

	/**
	 * Make a waiting task eligible sooner than it was.
	 *
	 * @return	bool	Whether anything moved.
	 */
	abstract public function pullForward(string $key, int $when): bool;

	/**
	 * Empty a queue, or every queue.
	 *
	 * @return	int		How many were dropped.
	 */
	abstract public function clear(string $queue = null): int;

	/**
	 * Waiting tasks, newest last.
	 *
	 * @return	QueuedTask[]
	 */
	abstract public function peek(string $queue = null, int $limit = 20): array;
}
