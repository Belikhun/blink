<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Ticker\Orbit\Sweep;

/**
 * Start whatever recurring work is due this minute.
 *
 * Meant for the system cron, once a minute:
 *
 * ```
 * * * * * * cd /var/www/site && php core/blink ticker:tick >> /dev/null 2>&1
 * ```
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerTick extends Command {
	public static string $usage = "ticker:tick {--at= : Sweep as though it were this time, as a timestamp} {--v|verbose : Report entries that were not due}";

	public static string $description = "Run the recurring work due now";

	public function run() {
		$at = $this -> option("at");
		$results = (new Sweep()) -> run(!empty($at) ? (int) $at : null);

		if (empty($results)) {
			if ($this -> option("verbose"))
				$this -> comment("Nothing due.");

			return Console::EXIT_OK;
		}

		foreach ($results as $result) {
			$line = sprintf("%-8s %s", $result["outcome"], $result["entry"]);

			if (!empty($result["detail"]))
				$line .= "  " . $result["detail"];

			match ($result["outcome"]) {
				Sweep::FAILED => $this -> error($line),
				Sweep::SKIPPED, Sweep::LOCKED => $this -> comment($line),
				default => $this -> success($line)
			};
		}

		// Always zero, even when an entry failed. Cron turns a non-zero exit
		// into mail, and a scheduler that mails on every failing entry is one
		// nobody reads. The failure is in `ticker:orbits` and an error report.
		return Console::EXIT_OK;
	}
}
