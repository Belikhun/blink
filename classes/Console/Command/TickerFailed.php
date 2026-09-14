<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Ticker;
use Blink\Ticker\Store\DatabaseStore;
use function Blink\relativeTime;

/**
 * List the tasks that ran out of attempts.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerFailed extends Command {
	public static string $usage = "ticker:failed {--limit=20 : How many to list}";

	public static string $description = "List the tasks that gave up";

	public function run() {
		$store = Ticker::store();

		if (!($store instanceof DatabaseStore)) {
			$this -> comment("The \"" . \CONFIG::$TICKER_STORE . "\" store keeps no failure list.");
			return Console::EXIT_OK;
		}

		$failures = $store -> failures((int) $this -> option("limit"));

		if (empty($failures)) {
			$this -> info("Nothing has failed.");
			return Console::EXIT_OK;
		}

		$rows = array();

		foreach ($failures as $failure) {
			$rows[] = array(
				$failure -> id,
				$failure -> queue,
				$failure -> label,
				$failure -> attempts,
				relativeTime((int) $failure -> failed),
				$failure -> report ?? "",
				$failure -> error
			);
		}

		$this -> table(array( "id", "queue", "task", "tries", "when", "report", "error" ), $rows);
		$this -> line();
		$this -> comment("Requeue one with `ticker:retry <id>`, or all of them with `ticker:retry --all`.");

		return Console::EXIT_OK;
	}
}
