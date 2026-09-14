<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Ticker;
use Blink\Ticker\Store\DatabaseStore;
use function Blink\relativeTime;

/**
 * Show what is waiting on the queues.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerStatus extends Command {
	public static string $usage = "ticker:status {queue? : Only show this queue} {--limit=20 : How many waiting tasks to list}";

	public static string $description = "Show the state of the queues";

	public function run() {
		global $DB;

		$store = Ticker::store();
		$queue = $this -> argument("queue");

		$this -> table(array(), array(
			array( "  store", \CONFIG::$TICKER_STORE ),
			array( "  default queue", \CONFIG::$TICKER_QUEUE ),
			array( "  waiting", $store -> size($queue) )
		));

		if (!($store instanceof DatabaseStore)) {
			$this -> line();
			$this -> comment("The \"" . \CONFIG::$TICKER_STORE . "\" store runs tasks inline, so nothing ever waits.");
			return Console::EXIT_OK;
		}

		$queued = $store -> peek($queue, (int) $this -> option("limit"));

		if (empty($queued)) {
			$this -> line();
			$this -> info("Nothing waiting.");
			return Console::EXIT_OK;
		}

		$now = time();
		$rows = array();

		foreach ($queued as $task) {
			$state = "waiting";

			if ($task -> reserved > 0)
				$state = ($task -> expires > $now) ? "running" : "lapsed";
			else if ($task -> available > $now)
				$state = "delayed";

			$rows[] = array(
				$task -> id,
				$task -> queue,
				$task -> label(),
				$state,
				$task -> attempts,
				relativeTime($task -> available)
			);
		}

		$this -> heading("waiting");
		$this -> table(array( "id", "queue", "task", "state", "tries", "due" ), $rows);

		$failures = (int) ($DB -> raw("SELECT COUNT(*) AS `total` FROM `" . DatabaseStore::FAILURES . "`")[0] -> total ?? 0);

		if ($failures > 0) {
			$this -> line();
			$this -> warning("{$failures} task(s) have failed. See `ticker:failed`.");
		}

		return Console::EXIT_OK;
	}
}
