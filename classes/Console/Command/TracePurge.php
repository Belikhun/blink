<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\DB;
use Blink\Trace\Entry;

/**
 * Delete audit entries past the retention window.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TracePurge extends Command {
	public static string $usage = "trace:purge {--days= : Delete entries older than this; defaults to CONFIG::\$TRACE_RETENTION_DAYS}"
		. " {--batch= : Entries per pass; defaults to CONFIG::\$TRACE_PURGE_BATCH}"
		. " {--budget=20 : Minutes one run may spend deleting; 0 for no limit}"
		. " {--f|force : Skip the confirmation}";

	public static string $description = "Delete old audit entries";

	public function run() {
		$days = ($this -> option("days") !== null)
			? max((int) $this -> option("days"), 0)
			: \CONFIG::$TRACE_RETENTION_DAYS;

		// Zero means keep everything, and a purge run from the orbit every
		// night must honour that rather than empty the table.
		if ($days <= 0) {
			$this -> info("Retention is unlimited; nothing to delete.");
			return Console::EXIT_OK;
		}

		$batch = ($this -> option("batch") !== null)
			? max((int) $this -> option("batch"), 1)
			: max(\CONFIG::$TRACE_PURGE_BATCH, 1);

		// The first purge after retention is reached, or after it is
		// shortened, faces the whole backlog at once. A bounded run works it
		// off over several nights instead of holding the orbit for hours.
		$budget = max((int) $this -> option("budget"), 0);
		$deadline = time() + ($budget * 60);

		$cutoff = time() - ($days * 86400);
		$query = Entry::query() -> where(Entry::col("created"), "<", $cutoff);
		$total = $query -> count();

		if ($total === 0) {
			$this -> info("Nothing older than {$days} day(s).");
			return Console::EXIT_OK;
		}

		if (!$this -> option("force") && !$this -> confirm("Delete {$total} audit entr" . ($total === 1 ? "y" : "ies") . " older than {$days} day(s)?", true)) {
			$this -> comment("Nothing was deleted.");
			return Console::EXIT_OK;
		}

		$deleted = 0;
		$exhausted = false;

		// In passes, so a table holding a year of entries never becomes one
		// statement holding locks for the duration.
		while ($budget === 0 || time() < $deadline) {
			$ids = $query -> ids("#", 0, $batch);

			if (empty($ids)) {
				$exhausted = true;
				break;
			}

			DB::table(Entry::$table) -> where("id", $ids) -> delete();
			$deleted += count($ids);

			if ($deleted < $total)
				$this -> comment("deleted {$deleted} of {$total}...");
		}

		$this -> success("Deleted {$deleted} audit entr" . ($deleted === 1 ? "y" : "ies") . ".");

		if (!$exhausted)
			$this -> comment("Time budget reached; the rest is purged on the next run.");

		return Console::EXIT_OK;
	}
}
