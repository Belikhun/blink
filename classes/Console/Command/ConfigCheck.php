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
	public static string $usage = "config:check";

	public static string $description = "Check the configuration for problems";

	public function run() {
		$problems = Config::check();
		$settings = count(Config::settings());
		$sections = count(Config::sections());

		if (empty($problems)) {
			$this -> success("{$settings} setting(s) across {$sections} section(s), nothing to report.");
			return Console::EXIT_OK;
		}

		$rows = array();

		foreach ($problems as $problem)
			$rows[] = array( $problem["key"], $problem["problem"] );

		$this -> table(array( "key", "problem" ), $rows);
		$this -> line();
		$this -> error(count($problems) . " problem(s) found.");

		return Console::EXIT_FAILURE;
	}
}
