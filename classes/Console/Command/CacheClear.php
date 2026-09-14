<?php

namespace Blink\Console\Command;

use Blink\Cache;
use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;

/**
 * Empty everything blink generated and can generate again.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CacheClear extends Command {
	public static string $usage = "cache:clear";

	public static string $description = "Clear the cache, the autoload map and the schema state";

	public function run() {
		$cleared = Cache::clearAll();
		$this -> success("Removed {$cleared} cache file(s).");

		\Blink\Autoload\purge();
		$this -> success("Cleared the autoload map.");

		// The state hash is what lets a request skip checking for pending
		// layers, so it goes with the rest.
		Terra::forgetState();
		$this -> success("Cleared the schema state.");

		return Console::EXIT_OK;
	}
}
