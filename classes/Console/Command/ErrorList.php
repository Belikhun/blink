<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use function Blink\convertSize;
use function Blink\relativeTime;

/**
 * List the error reports on disk, newest first.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ErrorList extends Command {
	public static string $usage = "error:list {--limit=20 : How many reports to show}";

	public static string $description = "List the stored error reports";

	public function run() {
		$limit = max((int) $this -> option("limit"), 1);
		$reports = glob(\CONFIG::$ERRORS_ROOT . "/*.report");

		if (empty($reports)) {
			$this -> info("No error reports.");
			return Console::EXIT_OK;
		}

		// Newest first: the one just filed is the one being looked for.
		usort($reports, fn ($a, $b) => filemtime($b) <=> filemtime($a));

		$rows = array();
		$total = count($reports);

		foreach (array_slice($reports, 0, $limit) as $path) {
			$rows[] = array(
				pathinfo($path, PATHINFO_FILENAME),
				relativeTime(filemtime($path)),
				convertSize(filesize($path))
			);
		}

		$this -> table(array( "id", "when", "size" ), $rows);
		$this -> line();
		$this -> comment("Showing " . count($rows) . " of {$total} report(s).");

		return Console::EXIT_OK;
	}
}
