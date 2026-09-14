<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Trace;
use Blink\Trace\Entry;
use function Blink\relativeTime;
use function Blink\trimString;

/**
 * Show the newest audit entries.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TraceList extends Command {
	public static string $usage = "trace:list {--limit=20 : How many entries to show}"
		. " {--level= : Only this level, by name or code}"
		. " {--mode= : Only this mode, by name or code}"
		. " {--component= : Only this component}"
		. " {--target= : Only entries about this model class}";

	public static string $description = "List the newest audit entries";

	public function run() {
		$limit = max((int) $this -> option("limit"), 1);
		$query = Entry::query() -> sort(Entry::col("id"), "DESC");

		if (($level = $this -> option("level")) !== null && $level !== "")
			$query -> where(Entry::col("level"), Trace::levels() -> normalize(is_numeric($level) ? (int) $level : $level));

		if (($mode = $this -> option("mode")) !== null && $mode !== "")
			$query -> where(Entry::col("mode"), Trace::modes() -> normalize(is_numeric($mode) ? (int) $mode : $mode));

		if (($component = $this -> option("component")) !== null && $component !== "")
			$query -> where(Entry::col("component"), $component);

		if (($target = $this -> option("target")) !== null && $target !== "")
			$query -> where(Entry::col("targetClass"), ltrim($target, "\\"));

		$entries = $query -> all(0, $limit);

		if (empty($entries)) {
			$this -> info("No audit entries match.");
			return Console::EXIT_OK;
		}

		$rows = array();

		foreach ($entries as $entry) {
			$rows[] = array(
				(string) $entry -> id,
				relativeTime($entry -> created),
				$entry -> getLevel()["name"],
				$entry -> getMode()["name"],
				$entry -> component,
				trimString($entry -> getDisplayMessage(), 72),
				$entry -> targetClass
					? Entry::humanizeClassName($entry -> targetClass) . " #{$entry -> targetID}"
					: ""
			);
		}

		$this -> table(array( "id", "when", "level", "mode", "component", "message", "target" ), $rows);
		$this -> line();
		$this -> comment("Showing " . count($rows) . " entr" . (count($rows) === 1 ? "y" : "ies") . ", newest first.");

		return Console::EXIT_OK;
	}
}
