<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;
use function Blink\relativeTime;

/**
 * Report which schema layers have been raised.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraStatus extends Command {
	public static string $usage = "terra:status {--pending : Only show what has not been raised}";

	public static string $description = "Show the state of every schema layer";

	public function run() {
		$rows = array();
		$pendingOnly = (bool) $this -> option("pending");

		foreach (Terra::status() as $row) {
			if ($pendingOnly && $row["applied"])
				continue;

			$state = $row["applied"] ? "applied" : "pending";

			if ($row["missing"])
				$state = "missing";

			$rows[] = array(
				$row["layer"],
				$state,
				$row["batch"] ?? "",
				!empty($row["raised"]) ? relativeTime($row["raised"]) : ""
			);
		}

		if (empty($rows)) {
			$this -> info($pendingOnly ? "Nothing pending." : "No layers found.");
			return Console::EXIT_OK;
		}

		$this -> table(array( "layer", "state", "batch", "raised" ), $rows);
		return Console::EXIT_OK;
	}
}
