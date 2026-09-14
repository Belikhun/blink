<?php

namespace Blink\Ticker\Task;

use Blink\Console;
use Blink\Ticker\Task;

/**
 * Run a console command from the queue.
 *
 * What a detached orbit entry queues, and a way to push a long-running command
 * onto a runner rather than holding up whatever asked for it.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CommandTask extends Task {
	/**
	 * The command line, written as it would be typed.
	 */
	public string $command;

	public function __construct(string $command) {
		$this -> command = trim($command);
	}

	public function run(): void {
		$tokens = preg_split("/\s+/", $this -> command);
		$name = (string) array_shift($tokens);

		$code = Console::call($name, $tokens ?: array());

		// The runner decides what to do about a failure, and it only knows a
		// task failed if the task says so.
		if ($code !== Console::EXIT_OK)
			throw new \RuntimeException("Command \"{$this -> command}\" exited with code {$code}.");
	}

	/**
	 * One queued run of a given command line at a time.
	 */
	public function key(): ?string {
		return "command:" . $this -> command;
	}

	public function label(): string {
		return "command: " . $this -> command;
	}
}
