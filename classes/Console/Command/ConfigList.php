<?php

namespace Blink\Console\Command;

use Blink\Config;
use Blink\Console;
use Blink\Console\Command;
use function Blink\trimString;

/**
 * Show what the site is configured with, and where each value came from.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ConfigList extends Command {
	public static string $usage = "config:list {section? : Only settings in this section}"
		. " {--changed : Only settings that differ from their default}"
		. " {--env : Only settings the environment can fill}"
		. " {--describe : Show what each setting is for}"
		. " {--reveal : Print secrets instead of masking them}";

	public static string $description = "Show the site's configuration";

	public function run() {
		$sections = Config::sections();
		$wanted = $this -> argument("section");

		if (!empty($wanted)) {
			$section = Config::section($wanted);

			if ($section === null) {
				$this -> error("No section named {$wanted}.");
				$this -> line("Known sections: " . implode(", ", array_keys($sections)));
				return Console::EXIT_USAGE;
			}

			$sections = array( $section -> prefix => $section );
		}

		$reveal = (bool) $this -> option("reveal");
		$describe = (bool) $this -> option("describe");
		$shown = 0;

		foreach ($sections as $prefix => $section) {
			$rows = array();

			foreach ($section -> settings() as $key => $setting) {
				if ($this -> option("changed") && !$setting -> isChanged())
					continue;

				if ($this -> option("env") && $setting -> env === null)
					continue;

				$row = array(
					$key,
					$setting -> type . ($setting -> nullable && $setting -> type !== "mixed" ? "?" : ""),
					trimString($setting -> display($reveal), 46),
					$setting -> source,
					$setting -> env ?? ""
				);

				if ($describe)
					$row[] = trimString($setting -> description ?? "", 60);

				$rows[] = $row;
			}

			if (empty($rows))
				continue;

			$this -> line();
			$this -> heading("{$prefix}  ({$section -> class})");

			$headers = array( "key", "type", "value", "from", "env" );

			if ($describe)
				$headers[] = "description";

			$this -> table($headers, $rows);
			$shown += count($rows);
		}

		$this -> line();

		if ($shown === 0) {
			$this -> info("Nothing matches.");
			return Console::EXIT_OK;
		}

		$this -> comment("{$shown} setting(s) in " . count($sections) . " section(s).");

		if (!$reveal)
			$this -> comment("Secrets are masked. Pass --reveal to print them.");

		return Console::EXIT_OK;
	}
}
