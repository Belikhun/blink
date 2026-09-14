<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\DB;
use Blink\Lightrail\Model\BatchedEvent;
use Blink\Lightrail\Task\BatchFlushTask;
use function Blink\relativeTime;

/**
 * Flush batched event buffers that are waiting.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LightrailFlush extends Command {
	/**
	 * How old an unclaimed row has to be before `--stale` treats its buffer as
	 * abandoned. Generous, since a legitimately long window should not look
	 * abandoned.
	 */
	const STALE_AFTER = 3600;

	public static string $usage = "lightrail:flush {--stale : Only buffers left behind with no flush coming} {--now : Run the flushes here instead of queueing them} {--list : Show the buffers and change nothing}";

	public static string $description = "Flush the batched event buffers";

	public function run() {
		$buffers = $this -> buffers();

		if (empty($buffers)) {
			$this -> info("No buffered events.");
			return Console::EXIT_OK;
		}

		$stale = (bool) $this -> option("stale");
		$cutoff = time() - static::STALE_AFTER;
		$targets = array();

		foreach ($buffers as $buffer) {
			if ($stale && (int) $buffer -> oldest > $cutoff)
				continue;

			$targets[] = $buffer;
		}

		if ($this -> option("list")) {
			$this -> render($targets ?: $buffers);
			return Console::EXIT_OK;
		}

		if (empty($targets)) {
			$this -> info($stale ? "Nothing has been left behind." : "Nothing to flush.");
			return Console::EXIT_OK;
		}

		foreach ($targets as $buffer) {
			$task = new BatchFlushTask((string) $buffer -> handler_key, (string) $buffer -> batch_key);
			$label = $task -> label() . "  " . $buffer -> waiting . " event(s)";

			if ($this -> option("now")) {
				$task -> run();
				$this -> success("flushed  " . $label);
				continue;
			}

			BatchFlushTask::flushNow((string) $buffer -> handler_key, (string) $buffer -> batch_key);
			$this -> success("queued   " . $label);
		}

		$this -> line();
		$this -> comment(count($targets) . " buffer(s).");

		return Console::EXIT_OK;
	}

	/**
	 * Unclaimed buffers, with how much is in each and how old the oldest is.
	 *
	 * @return	object[]
	 */
	protected function buffers(): array {
		global $DB;

		return $DB -> raw(
			"SELECT `handler_key`, `batch_key`, COUNT(*) AS `waiting`, MIN(`created`) AS `oldest`
				FROM `" . BatchedEvent::$table . "`
				WHERE `claim_token` = ''
				GROUP BY `handler_key`, `batch_key`
				ORDER BY `oldest`"
		);
	}

	/**
	 * @param	object[]	$buffers
	 */
	protected function render(array $buffers): void {
		$rows = array();

		foreach ($buffers as $buffer) {
			$rows[] = array(
				$buffer -> handler_key,
				$buffer -> batch_key,
				$buffer -> waiting,
				relativeTime((int) $buffer -> oldest)
			);
		}

		$this -> table(array( "handler", "batch", "waiting", "oldest" ), $rows);
	}
}
