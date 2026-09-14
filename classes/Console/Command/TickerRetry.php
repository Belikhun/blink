<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Console\Exception\InvalidInput;
use Blink\Ticker;
use Blink\Ticker\Store\DatabaseStore;

/**
 * Put failed tasks back on their queues.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerRetry extends Command {
	public static string $usage = "ticker:retry {id? : The failure to requeue} {--all : Requeue every failure}";

	public static string $description = "Requeue tasks that gave up";

	public function run() {
		$store = Ticker::store();

		if (!($store instanceof DatabaseStore)) {
			$this -> comment("The \"" . \CONFIG::$TICKER_STORE . "\" store keeps no failure list.");
			return Console::EXIT_OK;
		}

		$id = $this -> argument("id");
		$all = (bool) $this -> option("all");

		if (empty($id) && !$all)
			throw new InvalidInput("Name a failure to requeue, or pass --all.");

		// The attempt count starts over: a retry is a deliberate decision that
		// the thing which failed has been dealt with.
		$requeued = $store -> retry($all ? null : (int) $id);

		if ($requeued === 0) {
			$this -> info("Nothing to requeue.");
			return Console::EXIT_OK;
		}

		$this -> success("Requeued {$requeued} task(s).");
		return Console::EXIT_OK;
	}
}
