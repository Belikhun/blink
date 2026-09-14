<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;

/**
 * Drop the most recently raised batch of layers.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraRevert extends Command {
	public static string $usage = "terra:revert {--step=1 : How many batches to drop} {--f|force : Skip the confirmation}";

	public static string $description = "Drop the most recent schema layers";

	public function run() {
		$step = max((int) $this -> option("step"), 1);

		if (!$this -> option("force") && !$this -> confirm("Drop the last {$step} batch(es) of layers? This deletes data.")) {
			$this -> comment("Nothing was changed.");
			return Console::EXIT_OK;
		}

		$dropped = Terra::revert($step);

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
