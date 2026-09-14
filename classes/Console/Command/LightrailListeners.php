<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Lightrail;

/**
 * Show which handlers answer to which events.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LightrailListeners extends Command {
	public static string $usage = "lightrail:listeners {event? : Only handlers watching this event class} {--rebuild : Discover again before listing}";

	public static string $description = "List the registered event handlers";

	public function run() {
		if ($this -> option("rebuild")) {
			Lightrail::rebuildCache();
			$this -> comment("Rediscovered the observers.");
		}

		$filter = $this -> argument("event");
		$handlers = Lightrail::registry() -> allHandlers();
		$rows = array();

		foreach ($handlers as $handler) {
			if (!empty($filter) && !str_contains($handler -> eventClass, $filter))
				continue;

			$notes = array();

			if (!empty($handler -> context))
				$notes[] = "context";

			if (!empty($handler -> middleware))
				$notes[] = "mw:" . implode("+", $handler -> middleware);

			if ($handler -> batched())
				$notes[] = "window:" . ($handler -> batch["window"] ?? 0) . "s";

			$rows[] = array(
				$handler -> eventClass,
				$handler -> callable,
				$handler -> executionMode,
				$handler -> priority,
				$handler -> observer ? "observer" : "listener",
				$handler -> module ?? "",
				implode(" ", $notes)
			);
		}

		if (empty($rows)) {
			$this -> info(!empty($filter)
				? "Nothing is watching \"{$filter}\"."
				: "Nothing is registered.");

			return Console::EXIT_OK;
		}

		$this -> table(
			array( "event", "handler", "mode", "pri", "kind", "module", "notes" ),
			$rows
		);

		$this -> line();
		$this -> comment(count($rows) . " handler(s).");

		return Console::EXIT_OK;
	}
}
