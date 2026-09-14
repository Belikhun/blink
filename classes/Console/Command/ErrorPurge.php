<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use function Blink\convertSize;

/**
 * Delete stored error reports.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ErrorPurge extends Command {
	public static string $usage = "error:purge {--days=30 : Only delete reports older than this} {--all : Delete every report} {--f|force : Skip the confirmation}";

	public static string $description = "Delete old error reports";

	public function run() {
		$reports = glob(\CONFIG::$ERRORS_ROOT . "/*.report");

		if (empty($reports)) {
			$this -> info("No error reports.");
			return Console::EXIT_OK;
		}

		$all = (bool) $this -> option("all");
		$cutoff = time() - (max((int) $this -> option("days"), 0) * 86400);
		$targets = array();
		$freed = 0;

		foreach ($reports as $path) {
			if (!$all && filemtime($path) >= $cutoff)
				continue;

			$targets[] = $path;
			$freed += filesize($path);
		}

		if (empty($targets)) {
			$this -> info("Nothing old enough to delete.");
			return Console::EXIT_OK;
		}

		$question = "Delete " . count($targets) . " report(s), freeing " . convertSize($freed) . "?";

		if (!$this -> option("force") && !$this -> confirm($question, true)) {
			$this -> comment("Nothing was deleted.");
			return Console::EXIT_OK;
		}

		$deleted = 0;

		foreach ($targets as $path) {
			if (unlink($path))
				$deleted++;
		}

		$this -> success("Deleted {$deleted} report(s), freeing " . convertSize($freed) . ".");
		return Console::EXIT_OK;
	}
}
