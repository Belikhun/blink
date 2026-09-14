<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Lock;

/**
 * Force a lock open.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LockRelease extends Command {
	public static string $usage = "lock:release {name : The lock to release} {--store= : Which store it lives in} {--f|force : Skip the confirmation}";

	public static string $description = "Release a lock whoever holds it";

	public function run() {
		$name = $this -> argument("name");
		$store = Lock::store($this -> option("store") ?? \CONFIG::$LOCK_STORE);
		$holder = $store -> holder($name);

		if ($holder === null) {
			$this -> info("Nobody is holding \"{$name}\".");
			return Console::EXIT_OK;
		}

		$this -> line("Held by: " . $holder["owner"]);
		$this -> line("Expires in: " . max($holder["expires"] - time(), 0) . "s");
		$this -> line();

		// Every lock expires on its own. Forcing one open while its holder is
		// still working is how two processes end up doing the same job at
		// once, so this asks first.
		$this -> warning("Release this only when you know the process that took it is gone.");

		if (!$this -> option("force") && !$this -> confirm("Release \"{$name}\"?")) {
			$this -> comment("The lock was left alone.");
			return Console::EXIT_OK;
		}

		if (!$store -> forget($name)) {
			$this -> error("Could not release \"{$name}\".");
			return Console::EXIT_FAILURE;
		}

		$this -> success("Released \"{$name}\".");
		return Console::EXIT_OK;
	}
}
