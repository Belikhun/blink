<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Ticker;

/**
 * Empty a queue.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerClear extends Command {
	public static string $usage = "ticker:clear {queue? : Which queue to empty. Every queue when left out} {--f|force : Skip the confirmation}";

	public static string $description = "Throw away every queued task";

	public function run() {
		$queue = $this -> argument("queue");
		$waiting = Ticker::size($queue);

		if ($waiting === 0) {
			$this -> info("Nothing waiting.");
			return Console::EXIT_OK;
		}

		$what = !empty($queue)
			? "the \"{$queue}\" queue"
			: "every queue";

		// A runner holds its current task in memory, so this does not stop
		// work that is already under way.
		if (!$this -> option("force") && !$this -> confirm("Throw away {$waiting} task(s) from {$what}?"))
			return Console::EXIT_OK;

		$dropped = Ticker::store() -> clear($queue);
		$this -> success("Dropped {$dropped} task(s).");

		return Console::EXIT_OK;
	}
}
