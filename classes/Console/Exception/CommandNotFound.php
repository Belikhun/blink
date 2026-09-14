<?php

namespace Blink\Console\Exception;

use Blink\Exception\BaseException;

/**
 * No command answers to the name that was typed.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CommandNotFound extends BaseException {
	public string $command;

	/** @var string[] */
	public array $suggestions;

	/**
	 * @param	string		$command
	 * @param	string[]	$suggestions	Names close enough to be worth offering.
	 */
	public function __construct(string $command, array $suggestions = array()) {
		$this -> command = $command;
		$this -> suggestions = $suggestions;

		$details = !empty($suggestions)
			? "Did you mean: " . implode(", ", $suggestions) . "?"
			: "Run <code>blink list</code> to see what is available.";

		parent::__construct(
			CONSOLE_COMMAND_NOT_FOUND,
			"There is no command named \"{$command}\".",
			404,
			array( "command" => $command, "suggestions" => $suggestions ),
			$details
		);
	}
}
