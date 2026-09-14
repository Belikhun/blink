<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\ErrorPage;
use function Blink\convertSize;

/**
 * Delete stored error reports, and keep the index in step with what is left.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ErrorPurge extends Command {
	public static string $usage = "error:purge {--days=30 : Only delete reports older than this} {--keep= : Keep this many of the newest server errors and delete everything else} {--all : Delete every report} {--f|force : Skip the confirmation}";

	public static string $description = "Delete old error reports";

	public function run() {
		if ($this -> option("keep") !== null)
			return $this -> keepNewest(max((int) $this -> option("keep"), 0));

		$reports = glob(\CONFIG::$ERRORS_ROOT . "/*.report") ?: array();

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

		$pruned = ErrorPage::pruneMetas();

		$this -> success("Deleted {$deleted} report(s), freeing " . convertSize($freed) . ".");

		if ($pruned > 0)
			$this -> comment("Removed {$pruned} index entr" . ($pruned === 1 ? "y" : "ies") . " for them.");

		return Console::EXIT_OK;
	}

	protected function keepNewest(int $keep): int {
		$total = count(glob(\CONFIG::$ERRORS_ROOT . "/*.report") ?: array());

		if ($total === 0) {
			$this -> info("No error reports.");
			return Console::EXIT_OK;
		}

		$question = "Keep the {$keep} newest server error(s) and delete the rest of {$total} report(s)?";

		if (!$this -> option("force") && !$this -> confirm($question, true)) {
			$this -> comment("Nothing was deleted.");
			return Console::EXIT_OK;
		}

		$result = ErrorPage::purgeOldReports($keep);

		$this -> success("Deleted {$result["deleted"]} report(s), kept {$result["kept"]}.");
		return Console::EXIT_OK;
	}
}
