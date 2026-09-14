<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Ticker;
use Blink\Ticker\Runner;
use Blink\Ticker\RunnerOptions;
use Blink\Ticker\Store\QueuedTask;

/**
 * Drain a queue.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerRun extends Command {
	public static string $usage = "ticker:run {queue? : Which queue to drain}"
		. " {--once : Take one task and stop}"
		. " {--stop-when-empty : Stop as soon as nothing is left}"
		. " {--max-tasks=0 : Stop after this many tasks}"
		. " {--max-time=0 : Stop after this many seconds}"
		. " {--sleep=3 : Seconds to wait when the queue is empty}"
		. " {--memory=128 : Stop once this many MB are in use}"
		. " {--attempts=0 : Override every task's attempt count}"
		. " {--timeout=0 : Override every task's timeout}"
		. " {--quiet : Only report failures}";

	public static string $description = "Work through queued tasks";

	public function run() {
		$options = new RunnerOptions($this -> argument("queue"));
		$options -> once = (bool) $this -> option("once");
		$options -> stopWhenEmpty = (bool) $this -> option("stop-when-empty");
		$options -> maxTasks = (int) $this -> option("max-tasks");
		$options -> maxTime = (int) $this -> option("max-time");
		$options -> sleep = (int) $this -> option("sleep");
		$options -> memory = (int) $this -> option("memory");
		$options -> attempts = (int) $this -> option("attempts");
		$options -> timeout = (int) $this -> option("timeout");

		$quiet = (bool) $this -> option("quiet");

		if (!$quiet) {
			$this -> comment("Draining \"{$options -> queue}\", " . Ticker::size($options -> queue) . " task(s) waiting.");

			if (!function_exists("pcntl_alarm")) {
				$this -> warning(
					"pcntl is not available here, so a task that overruns its timeout cannot be interrupted. "
						. "It will be offered to another runner once its claim lapses."
				);
			}
		}

		$runner = new Runner($options);

		$runner -> onTask(function (QueuedTask $queued, string $outcome, ?\Throwable $e) use ($quiet) {
			$label = $queued -> label() . " #" . $queued -> id;

			switch ($outcome) {
				case Runner::OUTCOME_DONE:
					if (!$quiet)
						$this -> success("done     {$label}");
					break;

				case Runner::OUTCOME_RETRY:
					$this -> warning("retry    {$label}  attempt {$queued -> attempts}: " . $e -> getMessage());
					break;

				case Runner::OUTCOME_FAILED:
					$this -> error("failed   {$label}  after {$queued -> attempts} attempt(s): " . $e -> getMessage());
					break;
			}
		});

		$processed = $runner -> run();

		if (!$quiet)
			$this -> comment("Stopped after {$processed} task(s).");

		// A supervisor restarts a runner either way, but the distinct code
		// says whether it stopped because it was told to or because it ran out
		// of room.
		if ($runner -> hitMemoryLimit())
			return Console::EXIT_MEMORY;

		if ($runner -> stopping())
			return Console::EXIT_INTERRUPTED;

		return Console::EXIT_OK;
	}
}
