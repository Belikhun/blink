<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Ticker\Runner;

/**
 * Ask every running runner to stop after its current task.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TickerRestart extends Command {
	public static string $usage = "ticker:restart";

	public static string $description = "Stop the runners once they finish what they are doing";

	public function run() {
		Runner::restart();

		$this -> success("Runners will stop after their current task.");
		$this -> comment("Whatever supervises them is what starts them again.");

		return Console::EXIT_OK;
	}
}
