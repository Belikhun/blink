<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Ticker;
use Blink\Ticker\Orbit\Sweep;
use function Blink\relativeTime;

/**
 * Show the recurring entries and when they last ran.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerOrbits extends Command {
	public static string $usage = "ticker:orbits {--failing : Only show entries whose last run failed}";

	public static string $description = "List the recurring work and its last outcome";

	public function run() {
		global $DB;

		$entries = Ticker::orbit() -> entries();

		if (empty($entries)) {
			$this -> info("Nothing is scheduled.");
			return Console::EXIT_OK;
		}

		$state = array();

		foreach ($DB -> raw("SELECT * FROM `" . Sweep::TABLE . "`") as $row)
			$state[$row -> name] = $row;

		$rows = array();
		$failingOnly = (bool) $this -> option("failing");

		foreach ($entries as $entry) {
			$row = $state[$entry -> name] ?? null;
			$outcome = $row -> last_outcome ?? "";

			if ($failingOnly && $outcome !== Sweep::FAILED)
				continue;

			$notes = array();

			if ($entry -> alone)
				$notes[] = "alone";

			if ($entry -> detached)
				$notes[] = "detached";

			if ($entry -> condition !== null)
				$notes[] = "conditional";

			$rows[] = array(
				$entry -> name,
				$entry -> expression,
				$outcome ?: "never run",
				!empty($row -> last_run) ? relativeTime((int) $row -> last_run) : "",
				date("H:i", $entry -> pattern() -> next() ?? time()),
				(int) ($row -> failures ?? 0),
				implode(",", $notes)
			);
		}

		if (empty($rows)) {
			$this -> info($failingOnly ? "Nothing is failing." : "Nothing is scheduled.");
			return Console::EXIT_OK;
		}

		$this -> table(
			array( "entry", "pattern", "last", "when", "next", "fails", "notes" ),
			$rows
		);

		return Console::EXIT_OK;
	}
}
