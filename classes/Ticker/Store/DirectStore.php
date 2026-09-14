<?php

namespace Blink\Ticker\Store;

use Blink\Ticker\Task;

/**
 * A store that never queues anything: every task runs inline, where it was
 * handed over.
 *
 * For a site with nowhere to put a runner. The work still happens, but it
 * happens inside the request that asked for it, so the caller waits for it and
 * anything it raises comes straight back out.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DirectStore extends Store {
	/**
	 * Keys run during this request, so `queueUnique()` still collapses a burst
	 * the way it would with a real queue.
	 *
	 * @var array<string, bool>
	 */
	protected array $seen = array();

	public function push(Task $task, string $queue, int $delay = 0): ?int {
		// A delay has nowhere to live: there is no queue to wait in, and
		// sleeping would only hold up the request.
		$task -> run();
		return null;
	}

	public function pushUnique(Task $task, string $queue, int $delay = 0): ?int {
		$key = $task -> key();

		if (!empty($key)) {
			if (isset($this -> seen[$key]))
				return null;

			$this -> seen[$key] = true;
		}

		return $this -> push($task, $queue, $delay);
	}

	public function claim(string $queue, int $ttl): ?QueuedTask {
		return null;
	}

	public function release(QueuedTask $queued, int $delay = 0): bool {
		return false;
	}

	public function delete(QueuedTask $queued): bool {
		return false;
	}

	public function fail(QueuedTask $queued, \Throwable $e, string $reportId = null): void {}

	public function size(string $queue = null): int {
		return 0;
	}

	public function forget(string $key): int {
		unset($this -> seen[$key]);
		return 0;
	}

	public function pullForward(string $key, int $when): bool {
		return false;
	}

	public function clear(string $queue = null): int {
		$this -> seen = array();
		return 0;
	}

	public function peek(string $queue = null, int $limit = 20): array {
		return array();
	}
}
