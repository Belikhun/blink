<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\DB;
use Blink\Lightrail\Model\DispatchLog;
use Blink\Lightrail\Model\HandlerLog;

/**
 * Delete old dispatch trail.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LightrailPurge extends Command {
	public static string $usage = "lightrail:purge {--days=30 : Delete trail older than this}"
		. " {--keep-failed : Leave dispatches that failed}"
		. " {--batch=5000 : Dispatches per pass}"
		. " {--f|force : Skip the confirmation}";

	public static string $description = "Delete old dispatch trail";

	public function run() {
		global $DB;

		$cutoff = time() - (max((int) $this -> option("days"), 0) * 86400);
		$keepFailed = (bool) $this -> option("keep-failed");
		$batch = max((int) $this -> option("batch"), 1);

		$query = DispatchLog::query() -> where(DispatchLog::col("created"), "<", $cutoff);

		if ($keepFailed) {
			$query -> where(DispatchLog::col("status"), "<>", DispatchLog::STATUS_FAILED)
				-> where(DispatchLog::col("status"), "<>", DispatchLog::STATUS_PARTIAL);
		}

		$total = $query -> count();

		if ($total === 0) {
			$this -> info("Nothing old enough to delete.");
			return Console::EXIT_OK;
		}

		if (!$this -> option("force") && !$this -> confirm("Delete {$total} dispatch(es) and their handler rows?", true)) {
			$this -> comment("Nothing was deleted.");
			return Console::EXIT_OK;
		}

		$deleted = 0;

		// In passes, so a table with months of trail in it does not become one
		// enormous statement holding locks for the duration.
		while (true) {
			$ids = $query -> ids("#", 0, $batch);

			if (empty($ids))
				break;

			DB::table(HandlerLog::$table) -> where("dispatch_id", $ids) -> delete();
			DB::table(DispatchLog::$table) -> where("id", $ids) -> delete();

			$deleted += count($ids);
			$this -> comment("deleted {$deleted} of {$total}...");
		}

		$this -> success("Deleted {$deleted} dispatch(es).");
		return Console::EXIT_OK;
	}
}
