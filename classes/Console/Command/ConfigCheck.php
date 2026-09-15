<?php

namespace Blink\Console\Command;

use Blink\Config;
use Blink\Console;
use Blink\Console\Command;

/**
 * Report configuration problems that would otherwise surface as failures
 * later, somewhere less obvious.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ConfigCheck extends Command {
	public static string $usage = "config:check {--quiet : Report problems only, leaving out the notes}";

	public static string $description = "Check the configuration for problems";

	public function run() {
		$found = Config::check();
		$settings = count(Config::settings());
		$sections = count(Config::sections());

		$problems = array_values(array_filter($found, fn ($item) => $item["severity"] === Config::PROBLEM));
		$notes = array_values(array_filter($found, fn ($item) => $item["severity"] === Config::NOTE));

		if (!empty($problems)) {
			$this -> table(
				array( "key", "problem" ),
				array_map(fn ($item) => array( $item["key"], $item["message"] ), $problems)
			);

			$this -> line();
		}

		if (!empty($notes) && !$this -> option("quiet")) {
			$this -> table(
				array( "key", "note" ),
				array_map(fn ($item) => array( $item["key"], $item["message"] ), $notes)
			);

			$this -> line();
		}

		// Only a problem means the site is misconfigured. A note may well be
		// describing something this deployment meant to leave alone.
		if (empty($problems)) {
			$this -> success("{$settings} setting(s) across {$sections} section(s), nothing to fix.");
			return Console::EXIT_OK;
		}

		$this -> error(count($problems) . " problem(s) found.");
		return Console::EXIT_FAILURE;
	}
}
