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
	public static string $usage = "terra:status {--pending : Only show what has not been raised}"
		. " {--database= : Only rows on this instance}";

	public static string $description = "Show the state of every schema layer";

	public function run() {
		$rows = array();
		$pendingOnly = (bool) $this -> option("pending");
		$database = $this -> option("database") ?: null;

		foreach (Terra::status($database) as $row) {
			// Not raised yet, and still expected to be: a moved or missing
			// row is a leftover rather than work ahead.
			$ahead = !$row["applied"] && !in_array($row["state"], array( "moved", "missing" ), true);

			if ($pendingOnly && !$ahead)
				continue;

			$rows[] = array(
				$row["layer"],
				$row["database"],
				$row["state"],
				$row["batch"] ?? "",
				!empty($row["raised"]) ? relativeTime($row["raised"]) : "",
				$row["note"] ?? ""
			);
		}

		if (empty($rows)) {
			$this -> info($pendingOnly ? "Nothing pending." : "No layers found.");
		} else {
			$this -> table(array( "layer", "database", "state", "batch", "raised", "note" ), $rows);
		}

		foreach (Terra::unreachable() as $name => $reason)
			$this -> error("Instance {$name} could not be reached: {$reason}");

		return Console::EXIT_OK;
	}
}
