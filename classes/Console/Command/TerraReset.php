<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;

/**
 * Drop every applied layer, newest first.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraReset extends Command {
	public static string $usage = "terra:reset {--database= : Only this instance's ledger} {--f|force : Skip the confirmation}";

	public static string $description = "Drop every schema layer";

	public function run() {
		$database = $this -> option("database") ?: null;
		$where = ($database !== null) ? " on {$database}" : "";

		if (!$this -> option("force") && !$this -> confirm("Drop every layer{$where}? This deletes all data.")) {
			$this -> comment("Nothing was changed.");
			return Console::EXIT_OK;
		}

		$dropped = Terra::reset($database);

		if (empty($dropped)) {
			$this -> info("Nothing to drop.");
			return Console::EXIT_OK;
		}

		foreach ($dropped as $identifier)
			$this -> warning("dropped  " . $identifier);

		$this -> line();
		$this -> success(count($dropped) . " layer(s) dropped.");

		return Console::EXIT_OK;
	}
}
