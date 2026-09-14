<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Lock;
use function Blink\relativeTime;

/**
 * Show the locks currently held.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LockList extends Command {
	public static string $usage = "lock:list {--store= : Which store to read, database or file}";

	public static string $description = "List the locks currently held";

	public function run() {
		$store = $this -> option("store") ?? \CONFIG::$LOCK_STORE;
		$locks = Lock::store($store) -> all();

		if (empty($locks)) {
			$this -> info("No locks held in the \"{$store}\" store.");
			return Console::EXIT_OK;
		}

		$rows = array();

		foreach ($locks as $lock) {
			$rows[] = array(
				$lock["name"],
				relativeTime($lock["acquired"]),
				max($lock["expires"] - time(), 0) . "s",
				$lock["owner"]
			);
		}

		$this -> table(array( "name", "held for", "expires in", "taken by" ), $rows);
		return Console::EXIT_OK;
	}
}
