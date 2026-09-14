<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Ticker;
use Blink\Ticker\Store\DatabaseStore;

/**
 * Drop queued or failed tasks without running them.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerForget extends Command {
	public static string $usage = "ticker:forget {key? : Drop waiting tasks carrying this key} {--failures : Empty the failure list instead} {--f|force : Skip the confirmation}";

	public static string $description = "Drop queued or failed tasks";

	public function run() {
		$store = Ticker::store();
		$key = $this -> argument("key");

		if (!empty($key)) {
			// Only what is waiting. A task already being worked on belongs to
			// the runner holding it.
			$dropped = $store -> forget($key);
			$this -> success("Dropped {$dropped} waiting task(s) keyed \"{$key}\".");
			return Console::EXIT_OK;
		}

		if (!$this -> option("failures")) {
			$this -> warning("Name a key to drop, or pass --failures to empty the failure list.");
			return Console::EXIT_USAGE;
		}

		if (!($store instanceof DatabaseStore)) {
			$this -> comment("The \"" . \CONFIG::$TICKER_STORE . "\" store keeps no failure list.");
			return Console::EXIT_OK;
		}

		if (!$this -> option("force") && !$this -> confirm("Empty the failure list? The tasks in it are gone for good."))
			return Console::EXIT_OK;

		$dropped = $store -> flushFailures();
		$this -> success("Dropped {$dropped} failure(s).");

		return Console::EXIT_OK;
	}
}
