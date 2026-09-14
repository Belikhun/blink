<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\ErrorPage;
use function Blink\relativeTime;
use function Blink\trimString;

/**
 * List the stored error reports, newest first.
 *
 * Server errors come from the index, which is cheap to page through. Client
 * errors are only in the report files, so `--all` reads those instead.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ErrorList extends Command {
	public static string $usage = "error:list {--limit=20 : How many reports to show} {--page=1 : Which page of the index to show} {--all : Read every report file, client errors included}";

	public static string $description = "List the stored error reports";

	public function run() {
		$limit = max((int) $this -> option("limit"), 1);

		if ($this -> option("all"))
			return $this -> listFiles($limit);

		$page = max((int) $this -> option("page"), 1);
		$metas = ErrorPage::getMetas($limit, $page);
		$total = ErrorPage::getMetaCount();

		if (empty($metas)) {
			$this -> info($total > 0
				? "Nothing on page {$page}."
				: "No server error reports. Pass --all to include client errors.");

			return Console::EXIT_OK;
		}

		$rows = array();

		foreach ($metas as $meta) {
			$rows[] = array(
				$meta["id"] ?? "?",
				relativeTime((int) ($meta["created"] ?? 0)),
				(string) ($meta["status"] ?? ""),
				$meta["error"] ?? "?",
				trimString((string) ($meta["description"] ?? ""), 60),
				trim(($meta["method"] ?? "") . " " . ($meta["path"] ?? ""))
			);
		}

		$this -> table(array( "id", "when", "status", "error", "description", "request" ), $rows);
		$this -> line();
		$this -> comment("Page {$page}: showing " . count($rows) . " of {$total} server error(s).");

		return Console::EXIT_OK;
	}

	protected function listFiles(int $limit): int {
		$reports = glob(\CONFIG::$ERRORS_ROOT . "/*.report") ?: array();

		if (empty($reports)) {
			$this -> info("No error reports.");
			return Console::EXIT_OK;
		}

		usort($reports, fn ($a, $b) => filemtime($b) <=> filemtime($a));

		$rows = array();

		foreach (array_slice($reports, 0, $limit) as $path) {
			$id = pathinfo($path, PATHINFO_FILENAME);

			try {
				$report = ErrorPage::get($id);
				list($error) = $report -> info();

				$rows[] = array(
					$id,
					relativeTime($report -> created),
					(string) $report -> status,
					$error,
					trim("{$report -> method} {$report -> path}")
				);
			} catch (\Throwable $e) {
				// Written by a build this one cannot read back, most likely.
				$rows[] = array( $id, relativeTime(filemtime($path)), "?", "[unreadable]", "" );
			}
		}

		$this -> table(array( "id", "when", "status", "error", "request" ), $rows);
		$this -> line();
		$this -> comment("Showing " . count($rows) . " of " . count($reports) . " report(s).");

		return Console::EXIT_OK;
	}
}
