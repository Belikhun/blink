<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;

/**
 * Drop every table and raise the whole stack again from nothing.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraRebuild extends Command {
	public static string $usage = "terra:rebuild {--database= : Only this instance} {--f|force : Skip the confirmation}";

	public static string $description = "Drop everything and raise the schema from scratch";

	public function run() {
		// Not a revert: this drops every table in the database, including ones
		// no layer put there.
		$database = $this -> option("database") ?: null;
		$where = ($database !== null) ? " on {$database}" : " on every instance";

		if (!$this -> option("force") && !$this -> confirm("Drop EVERY table{$where} and rebuild? This deletes all data.")) {
			$this -> comment("Nothing was changed.");
			return Console::EXIT_OK;
		}

		$raised = Terra::rebuild($database);

		foreach ($raised as $identifier)
			$this -> success("raised  " . $identifier);

		$this -> line();
		$this -> success("Rebuilt with " . count($raised) . " layer(s).");

		return Console::EXIT_OK;
	}
}
