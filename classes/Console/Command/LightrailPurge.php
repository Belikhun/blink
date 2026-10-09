<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\DB;
use Blink\Lightrail\Model\DispatchLog;
use Blink\Lightrail\Model\HandlerLog;

/**
 * Trim the dispatch trail.
 *
 * A dispatch whose every handler succeeded has told its story once it has
 * been looked at, so it is kept only briefly. Anything else, failed or
 * partial dispatches and dispatches still pending long after they should
 * have settled, is what the trail exists to explain, so it stays for days.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LightrailPurge extends Command {
	public static string $usage = "lightrail:purge {--days=7 : Delete trail older than this}"
		. " {--success-hours=1 : Delete dispatches whose every handler succeeded once older than this; 0 keeps them for --days}"
		. " {--keep-failed : Leave dispatches that failed}"
		. " {--batch=1000 : Dispatches per pass}"
		. " {--f|force : Skip the confirmation}";

	public static string $description = "Delete old dispatch trail";

	public function run() {
		$now = time();
		$cutoff = $now - (max((int) $this -> option("days"), 0) * 86400);
		$successHours = max((int) $this -> option("success-hours"), 0);
		$successCutoff = $now - ($successHours * 3600);
		$keepFailed = (bool) $this -> option("keep-failed");
		$batch = max((int) $this -> option("batch"), 1);

		$queries = array();

		if ($successHours > 0) {
			$queries[] = DispatchLog::query()
				-> where(DispatchLog::col("status"), DispatchLog::STATUS_SUCCESS)
				-> where(DispatchLog::col("created"), "<", $successCutoff);
		}

		$everything = DispatchLog::query()
			-> where(DispatchLog::col("created"), "<", $cutoff);

		// The successes the first window already covers are left out, so the
		// two windows never count or delete the same row.
		if ($successHours > 0) {
			$everything -> where(function ($group) use ($successCutoff) {
				$group -> where(DispatchLog::col("status"), "<>", DispatchLog::STATUS_SUCCESS)
					-> whereOr(DispatchLog::col("created"), ">=", $successCutoff);
			});
		}

		if ($keepFailed) {
			$everything -> where(DispatchLog::col("status"), "<>", DispatchLog::STATUS_FAILED)
				-> where(DispatchLog::col("status"), "<>", DispatchLog::STATUS_PARTIAL);
		}

		$queries[] = $everything;

		$total = 0;

		foreach ($queries as $query)
			$total += $query -> count();

		$orphans = $this -> orphanHandlers() -> count();

		if ($total === 0 && $orphans === 0) {
			$this -> info("Nothing old enough to delete.");
			return Console::EXIT_OK;
		}

		$prompt = "Delete {$total} dispatch(es) and their handler rows"
			. ($orphans > 0 ? ", and {$orphans} handler row(s) whose dispatch is gone" : "")
			. "?";

		if (!$this -> option("force") && !$this -> confirm($prompt, true)) {
			$this -> comment("Nothing was deleted.");
			return Console::EXIT_OK;
		}

		$deleted = 0;
		$handlers = 0;

		// In passes, so a table with days of trail in it does not become one
		// enormous statement holding locks for the duration.
		foreach ($queries as $query) {
			while (true) {
				$ids = $query -> ids("#", 0, $batch);

				if (empty($ids))
					break;

				$handlers += DB::table(HandlerLog::$table) -> where("dispatch_id", $ids) -> delete();
				DB::table(DispatchLog::$table) -> where("id", $ids) -> delete();

				$deleted += count($ids);
				$this -> comment("deleted {$deleted} of {$total}...");
			}
		}

		$orphansDeleted = 0;

		while (true) {
			$ids = $this -> orphanHandlers() -> ids(HandlerLog::col("id"), 0, $batch);

			if (empty($ids))
				break;

			DB::table(HandlerLog::$table) -> where("id", $ids) -> delete();
			$orphansDeleted += count($ids);
		}

		$this -> success("Deleted {$deleted} dispatch(es), {$handlers} handler row(s) with them, and {$orphansDeleted} orphan handler row(s).");
		return Console::EXIT_OK;
	}

	/**
	 * Handler rows whose dispatch no longer exists, which an interrupted
	 * purge or a dispatch deleted by hand can leave behind.
	 */
	protected function orphanHandlers() {
		return DB::table(HandlerLog::$table)
			-> leftJoin(DispatchLog::$table, DispatchLog::col("id"), HandlerLog::col("dispatchID"))
			-> where(DispatchLog::col("id"), null)
			-> sort(HandlerLog::col("id"), "ASC");
	}
}
