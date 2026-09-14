<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;

/**
 * Raise every pending schema layer.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraApply extends Command {
	public static string $usage = "terra:apply {--step= : Stop after this many layers} {--f|force : Skip the confirmation}";

	public static string $description = "Raise pending schema layers";

	public function run() {
		$pending = Terra::pending();

		if (empty($pending)) {
			$this -> info("Nothing to raise, the schema is up to date.");
			return Console::EXIT_OK;
		}

		$this -> line("Pending layers:");

		foreach (array_keys($pending) as $identifier)
			$this -> line("  " . $identifier);

		$this -> line();

		if (!$this -> option("force") && !$this -> confirm("Raise " . count($pending) . " layer(s)?", true)) {
			$this -> comment("Nothing was changed.");
			return Console::EXIT_OK;
		}

		$raised = Terra::apply((int) ($this -> option("step") ?? 0));

		foreach ($raised as $identifier)
			$this -> success("raised  " . $identifier);

		$this -> line();
		$this -> success(count($raised) . " layer(s) raised.");

		return Console::EXIT_OK;
	}
}
