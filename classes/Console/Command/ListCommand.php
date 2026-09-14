<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;

/**
 * Show every command the console knows about.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ListCommand extends Command {
	public static string $usage = "list {group? : Only show commands in this group}";

	public static string $description = "List the available commands";

	public function run() {
		$group = $this -> argument("group");

		$this -> line("blink " . \Blink::version() . ", " . \CONFIG::$APP_NAME . " " . \Blink::appVersion());
		$this -> line();
		$this -> line("Usage: blink <command> [arguments] [options]");

		$grouped = array();

		foreach (Console::commands() as $name => $class) {
			if ($class::$hidden)
				continue;

			// Everything before the first colon is the group; a name without
			// one belongs to the unnamed group shown first.
			$prefix = str_contains($name, ":")
				? explode(":", $name)[0]
				: "";

			if (!empty($group) && $prefix !== $group)
				continue;

			$grouped[$prefix][$name] = $class::$description;
		}

		if (empty($grouped)) {
			$this -> warning("No commands" . (!empty($group) ? " in the \"{$group}\" group." : "."));
			return Console::EXIT_USAGE;
		}

		ksort($grouped);

		foreach ($grouped as $prefix => $commands) {
			$this -> heading($prefix === "" ? "general" : $prefix);
			$rows = array();

			foreach ($commands as $name => $description)
				$rows[] = array( "  " . $name, $description );

			$this -> table(array(), $rows);
		}

		$this -> line();
		return Console::EXIT_OK;
	}
}
