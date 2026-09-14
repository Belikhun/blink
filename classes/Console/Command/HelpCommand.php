<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Console\Exception\CommandNotFound;

/**
 * Show one command's signature in full.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class HelpCommand extends Command {
	public static string $usage = "help {command : The command to describe}";

	public static string $description = "Describe a command and what it takes";

	public function run() {
		$name = $this -> argument("command");
		$class = Console::find($name);

		if ($class === null)
			throw new CommandNotFound($name, Console::suggest($name));

		$spec = $class::spec();

		$this -> line();
		$this -> line($class::$description);
		$this -> line();
		$this -> line("Usage: blink " . $spec -> synopsis());

		if (!empty($spec -> arguments)) {
			$this -> heading("arguments");
			$rows = array();

			foreach ($spec -> arguments as $argument) {
				$note = $argument -> description ?? "";

				if (!$argument -> required && $argument -> default !== null && !is_array($argument -> default))
					$note = trim($note . " [default: {$argument -> default}]");

				$rows[] = array( "  " . $argument -> synopsis(), $note );
			}

			$this -> table(array(), $rows);
		}

		if (!empty($spec -> options)) {
			$this -> heading("options");
			$rows = array();

			foreach ($spec -> options as $option) {
				$note = $option -> description ?? "";

				if ($option -> takesValue && $option -> default !== null)
					$note = trim($note . " [default: {$option -> default}]");

				$rows[] = array( "  " . $option -> synopsis(), $note );
			}

			$this -> table(array(), $rows);
		}

		$this -> line();
		return Console::EXIT_OK;
	}
}
