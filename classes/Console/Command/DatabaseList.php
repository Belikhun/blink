<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\DB\Database;

/**
 * List every database instance and whether it can be reached.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseList extends Command {
	public static string $usage = "database:list";

	public static string $description = "List the database instances and check each connects";

	public function run() {
		$rows = array();
		$failed = 0;

		foreach (Database::names() as $name) {
			try {
				$database = Database::get($name);
				$about = $database -> describe();

				$rows[] = array(
					$name,
					$about["driver"],
					$database -> family(),
					$about["target"],
					count($database -> tables()) . " tables"
				);
			} catch (\Throwable $e) {
				$failed++;
				$rows[] = array( $name, "", "", "", "failed: " . strip_tags($e -> getMessage()) );
			}
		}

		$this -> table(array( "instance", "driver", "family", "target", "state" ), $rows);

		if ($failed > 0) {
			$this -> error("{$failed} instance(s) could not be connected.");
			return Console::EXIT_FAILURE;
		}

		return Console::EXIT_OK;
	}
}
