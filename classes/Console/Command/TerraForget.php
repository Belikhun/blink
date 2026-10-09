<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;

/**
 * Remove a layer's ledger row without touching the schema.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraForget extends Command {
	public static string $usage = "terra:forget {layer : Layer identifier, as terra:status lists it}"
		. " {--database= : Only this instance's ledger}"
		. " {--f|force : Skip the confirmation}";

	public static string $description = "Forget that a layer was raised, leaving the schema as it is";

	public function run() {
		$layer = trim((string) $this -> argument("layer"));
		$database = $this -> option("database") ?: null;

		$where = ($database !== null) ? "on {$database}" : "on every instance";

		// The schema stays as it is, so the layer runs again on the next
		// apply. That is the point for a restored dump, and a mistake for a
		// live table, so it is asked about.
		if (!$this -> option("force") && !$this -> confirm("Forget that {$layer} was raised {$where}? Its next apply will run it again.")) {
			$this -> comment("Nothing was changed.");
			return Console::EXIT_OK;
		}

		$removed = Terra::forget($layer, $database);

		if (empty($removed)) {
			$this -> info("No ledger records {$layer} {$where}.");
			return Console::EXIT_OK;
		}

		foreach ($removed as $name)
			$this -> warning("forgot  {$layer}  [{$name}]");

		return Console::EXIT_OK;
	}
}
