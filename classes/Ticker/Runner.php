<?php

namespace Blink\Ticker;

use Blink\Cache;
use Blink\ErrorPage;
use Blink\Essence;
use Blink\Metric;
use Blink\Model;
use Blink\Ticker;
use Blink\Ticker\Exception\TaskUnserializable;
use Blink\Ticker\Store\QueuedTask;
use Blink\Trace\Writer as TraceWriter;

/**
 * Drains a queue, one task at a time.
 *
 * The loop is: claim a task, run it, take it off the queue or put it back.
 * Between tasks everything the last one left behind is dropped, because a
 * runner outlives a request and the caches a request can afford to keep would
 * only grow.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Runner {
	/**
	 * Cache holding the moment runners were last asked to stop.
	 */
	const RESTART_CACHE = "ticker.restart";

	const OUTCOME_DONE = "done";
	const OUTCOME_RETRY = "retry";
	const OUTCOME_FAILED = "failed";

	protected RunnerOptions $options;

	/**
	 * Set by a signal handler, checked between tasks. A task in flight is
	 * always allowed to finish: killing it halfway is what leaves a queue full
	 * of half-done work.
	 */
	protected bool $stopping = false;

	protected int $started;

	protected int $processed = 0;

	/**
	 * Called after each task with the queued row and the outcome, so a command
	 * can report progress without the runner knowing about output.
	 *
	 * @var ?callable
	 */
	protected $reporter = null;

	public function __construct(RunnerOptions $options = null) {
		$this -> options = $options ?? new RunnerOptions();
	}

	public function onTask(callable $reporter): static {
		$this -> reporter = $reporter;
		return $this;
	}

	/**
	 * Work until one of the stopping conditions is met.
	 *
	 * @return	int		How many tasks were processed.
	 */
	public function run(): int {
		$this -> started = time();
		$this -> listenForSignals();

		$store = Ticker::store();
		$restartedAt = $this -> restartStamp();

		while (true) {
			if ($this -> shouldStop($restartedAt))
				break;

			$queued = $store -> claim($this -> options -> queue, $this -> claimTtl());

			if ($queued === null) {
				if ($this -> options -> once || $this -> options -> stopWhenEmpty)
					break;

				$this -> rest();
				continue;
			}

			$this -> process($queued);
			$this -> processed++;

			if ($this -> options -> once)
				break;
		}

		return $this -> processed;
	}

	public function processed(): int {
		return $this -> processed;
	}

	public function stopping(): bool {
		return $this -> stopping;
	}

	/**
	 * Ask every running runner to stop once it finishes what it is doing.
	 *
	 * A timestamp in the cache rather than a signal, because a signal only
	 * reaches a process whose id you know, and the point is to reach all of
	 * them from anywhere.
	 */
	public static function restart(): int {
		$now = time();

		Cache::instance(static::RESTART_CACHE)
			-> setContent($now)
			-> save();

		return $now;
	}

	/**
	 * Run one task and decide what becomes of it.
	 */
	protected function process(QueuedTask $queued): void {
		$store = Ticker::store();

		try {
			$task = $queued -> task();
		} catch (TaskUnserializable $e) {
			// Retrying cannot help: the class is gone. File it and move on.
			$store -> fail($queued, $e, $this -> report($e));
			$this -> notify($queued, static::OUTCOME_FAILED, $e);
			$this -> reset();
			return;
		}

		$attempts = $this -> options -> attempts > 0
			? $this -> options -> attempts
			: max($task -> attempts, 1);

		try {
			$this -> guard($this -> taskTimeout($task));
			$task -> run();
			$this -> unguard();

			$store -> delete($queued);
			$this -> notify($queued, static::OUTCOME_DONE, null);
		} catch (\Throwable $e) {
			$this -> unguard();

			if ($queued -> attempts < $attempts) {
				$store -> release($queued, $task -> holdoffFor($queued -> attempts));
				$this -> notify($queued, static::OUTCOME_RETRY, $e);
			} else {
				// The task gets a say before the failure is filed, and a
				// failing `failed()` must not replace the real error.
				try {
					$task -> failed($e);
				} catch (\Throwable $handlerError) {
					error_log("[blink][ticker] {$queued -> label()} -> failed() raised: {$handlerError}");
				}

				$store -> fail($queued, $e, $this -> report($e));
				$this -> notify($queued, static::OUTCOME_FAILED, $e);
			}
		} finally {
			$this -> reset();
		}
	}

	/**
	 * Drop everything the last task accumulated.
	 */
	protected function reset(): void {
		// The task's audit entries go out now rather than when the runner
		// eventually exits, which may be days from now.
		try {
			Essence::make(TraceWriter::class) -> flush();
		} catch (\Throwable $e) {
			error_log("[blink][ticker] could not flush the audit log: {$e}");
		}

		Model::flushInstances(true);
		Metric::reset();
		Essence::clearConstructorStack();

		if (function_exists("gc_collect_cycles"))
			gc_collect_cycles();
	}

	protected function shouldStop(int $restartedAt): bool {
		if ($this -> stopping)
			return true;

		$options = $this -> options;

		if ($options -> maxTasks > 0 && $this -> processed >= $options -> maxTasks)
			return true;

		if ($options -> maxTime > 0 && (time() - $this -> started) >= $options -> maxTime)
			return true;

		if ($options -> memory > 0 && memory_get_usage(true) >= $options -> memory * 1024 * 1024)
			return true;

		// A restart asked for after this runner started is meant for it.
		if ($this -> restartStamp() > $restartedAt)
			return true;

		return false;
	}

	/**
	 * Whether the runner stopped because it ran out of memory, which the
	 * command reports with its own exit code so a supervisor can tell it from
	 * a clean finish.
	 */
	public function hitMemoryLimit(): bool {
		return $this -> options -> memory > 0
			&& memory_get_usage(true) >= $this -> options -> memory * 1024 * 1024;
	}

	protected function rest(): void {
		// Broken into one-second naps so a stop signal is noticed promptly
		// rather than after the whole sleep.
		for ($slept = 0; $slept < max($this -> options -> sleep, 1); $slept++) {
			if ($this -> stopping)
				return;

			sleep(1);
			$this -> dispatchSignals();
		}
	}

	protected function claimTtl(): int {
		return $this -> options -> timeout > 0
			? $this -> options -> timeout
			: max(\CONFIG::$TICKER_RETRY_AFTER, 1);
	}

	protected function taskTimeout(Task $task): int {
		if ($this -> options -> timeout > 0)
			return $this -> options -> timeout;

		return $task -> timeout > 0
			? $task -> timeout
			: max(\CONFIG::$TICKER_RETRY_AFTER, 1);
	}

	/**
	 * Arm an alarm that interrupts a task which runs past its timeout.
	 *
	 * Only where `pcntl` is available, which excludes Windows. Everywhere else
	 * the claim expiry is the backstop: the task is not interrupted, but
	 * another runner may take it over once the claim lapses.
	 *
	 * `set_time_limit()` is deliberately not used, because on the command line
	 * it counts processor time, and a task waiting on a query or an HTTP call
	 * uses almost none of that.
	 */
	protected function guard(int $seconds): void {
		if (!function_exists("pcntl_alarm"))
			return;

		pcntl_alarm(max($seconds, 1));
	}

	protected function unguard(): void {
		if (function_exists("pcntl_alarm"))
			pcntl_alarm(0);
	}

	protected function listenForSignals(): void {
		if (function_exists("pcntl_async_signals") && function_exists("pcntl_signal")) {
			pcntl_async_signals(true);

			pcntl_signal(SIGTERM, fn () => $this -> stopping = true);
			pcntl_signal(SIGINT, fn () => $this -> stopping = true);
			pcntl_signal(SIGALRM, function () {
				throw new \RuntimeException("The task ran past its timeout.");
			});

			return;
		}

		// Windows has no pcntl, but the console can still report Ctrl+C.
		if (function_exists("sapi_windows_set_ctrl_handler"))
			sapi_windows_set_ctrl_handler(fn () => $this -> stopping = true);
	}

	protected function dispatchSignals(): void {
		if (function_exists("pcntl_signal_dispatch"))
			pcntl_signal_dispatch();
	}

	protected function restartStamp(): int {
		$cache = Cache::instance(static::RESTART_CACHE);

		if (!$cache -> fetch() || !$cache -> validate())
			return 0;

		return (int) $cache -> content();
	}

	/**
	 * File an error report, returning its id.
	 */
	protected function report(\Throwable $e): ?string {
		try {
			return ErrorPage::report($e);
		} catch (\Throwable $reportError) {
			error_log("[blink][ticker] could not file a report: {$reportError}");
			return null;
		}
	}

	protected function notify(QueuedTask $queued, string $outcome, ?\Throwable $e): void {
		if ($this -> reporter !== null)
			($this -> reporter)($queued, $outcome, $e);
	}
}
