<?php

namespace Blink\Ticker;

/**
 * How a {@see Runner} should behave.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RunnerOptions {
	public string $queue;

	/**
	 * Take one task and stop, whether or not anything was there.
	 */
	public bool $once = false;

	/**
	 * Stop as soon as the queue runs dry, rather than waiting for more.
	 */
	public bool $stopWhenEmpty = false;

	/**
	 * Stop after this many tasks. Zero for no limit.
	 */
	public int $maxTasks = 0;

	/**
	 * Stop after this many seconds. Zero for no limit.
	 */
	public int $maxTime = 0;

	/**
	 * Seconds to wait when the queue is empty.
	 */
	public int $sleep = 3;

	/**
	 * Megabytes of memory after which the runner stops.
	 *
	 * A long-running PHP process fragments its heap whatever it does, so it is
	 * meant to be restarted rather than kept alive forever. Stopping cleanly
	 * near the limit is what keeps the operating system from killing it
	 * halfway through a task.
	 */
	public int $memory = 128;

	/**
	 * Override every task's own attempt count. Zero leaves them alone.
	 */
	public int $attempts = 0;

	/**
	 * Override every task's own timeout. Zero leaves them alone.
	 */
	public int $timeout = 0;

	public function __construct(string $queue = null) {
		$this -> queue = $queue ?? \CONFIG::$TICKER_QUEUE;
	}
}
