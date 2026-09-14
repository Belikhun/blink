<?php

namespace Blink;

use Blink\Console\Command;
use Blink\Console\Exception\CommandNotFound;
use Blink\Console\Exception\InvalidInput;
use Blink\Console\Input\Input;
use Blink\Console\Output;
use Blink\ErrorPage;
use Blink\ErrorPage\TerminalRenderer;
use Blink\Exception\InvalidDefinition;

/**
 * Blink's command line.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Console {
	const EXIT_OK = 0;

	const EXIT_FAILURE = 1;

	/**
	 * The command was called wrongly: a name that does not exist, a missing
	 * argument, an option it does not take.
	 */
	const EXIT_USAGE = 2;

	const EXIT_MEMORY = 12;

	/**
	 * Stopped by a signal. 128 plus the signal number is the shell's
	 * convention, and 2 is SIGINT.
	 */
	const EXIT_INTERRUPTED = 130;

	const EXIT_FATAL = 255;

	/**
	 * Registered commands, mapped by the name their usage line declares.
	 *
	 * @var array<string, class-string<Command>>
	 */
	protected static array $commands = array();

	protected static ?Output $output = null;

	protected static bool $booted = false;

	/**
	 * Add commands to the console.
	 *
	 * @param	class-string<Command>	...$classes
	 */
	public static function register(string ...$classes): void {
		foreach ($classes as $class) {
			$class = ltrim($class, "\\");

			if (!is_subclass_of($class, Command::class))
				throw new InvalidDefinition($class, Command::class);

			static::$commands[$class::name()] = $class;
		}
	}

	/**
	 * Collect what every loaded module declares in `commands()`.
	 *
	 * Runs once. A module listing a command twice, or two modules listing the
	 * same one, is harmless: the map is keyed by name.
	 */
	public static function boot(): void {
		if (static::$booted)
			return;

		static::$booted = true;

		foreach (\Blink::modules() as $module)
			static::register(...$module -> commands());
	}

	/**
	 * @return	array<string, class-string<Command>>
	 */
	public static function commands(): array {
		static::boot();
		ksort(static::$commands);

		return static::$commands;
	}

	/**
	 * @return	?class-string<Command>
	 */
	public static function find(string $name): ?string {
		static::boot();
		return static::$commands[$name] ?? null;
	}

	public static function output(): Output {
		if (static::$output === null)
			static::$output = new Output();

		return static::$output;
	}

	public static function setOutput(Output $output): void {
		static::$output = $output;
	}

	/**
	 * Run the command named in `$argv` and return the process exit code.
	 *
	 * @param	string[]	$argv	The raw `$argv`, script name and all.
	 */
	public static function run(array $argv): int {
		$output = static::output();

		// Drop the script name, then take the first token that is not an
		// option as the command name.
		array_shift($argv);
		$name = null;

		foreach ($argv as $index => $token) {
			if (str_starts_with($token, "-"))
				continue;

			$name = $token;
			unset($argv[$index]);
			break;
		}

		// No name at all means the caller wants to know what is available.
		if ($name === null)
			$name = "list";

		return static::call($name, array_values($argv), $output);
	}

	/**
	 * Run one command by name.
	 *
	 * @param	string		$name		Command name.
	 * @param	string[]	$tokens		Arguments and options, without the name.
	 * @param	?Output		$output
	 * @return	int			Exit code.
	 */
	public static function call(string $name, array $tokens = array(), Output $output = null): int {
		$output = $output ?? static::output();

		try {
			$class = static::find($name);

			if ($class === null)
				throw new CommandNotFound($name, static::suggest($name));

			$input = Input::parse($class::spec(), $tokens);

			/** @var Command */
			$command = Essence::make($class);
			$command -> bind($input, $output);

			$code = Essence::call([ $command, "run" ]);
			return is_int($code) ? $code : static::EXIT_OK;
		} catch (CommandNotFound $e) {
			$output -> error($e -> description);

			if (!empty($e -> suggestions)) {
				$output -> line();
				$output -> line("Did you mean one of these?");

				foreach ($e -> suggestions as $suggestion)
					$output -> line("  " . $suggestion);
			}

			return static::EXIT_USAGE;
		} catch (InvalidInput $e) {
			$output -> error($e -> description);

			if (!empty($class))
				$output -> line("Usage: " . $class::spec() -> synopsis());

			return static::EXIT_USAGE;
		} catch (\Throwable $e) {
			// A command that fails gets the same report a failed request does,
			// so `error:list` finds it and the id at the bottom leads to it.
			try {
				TerminalRenderer::print(ErrorPage::fromException($e) -> save());
			} catch (\Throwable $reportError) {
				$output -> error(get_class($e) . ": " . strip_tags($e -> getMessage()));
				$output -> comment(getRelativePath($e -> getFile()) . ":" . $e -> getLine());
			}

			return static::EXIT_FAILURE;
		}
	}

	/**
	 * Command names close enough to what was typed to be worth offering.
	 *
	 * @return	string[]
	 */
	public static function suggest(string $name): array {
		$suggestions = array();

		foreach (array_keys(static::commands()) as $candidate) {
			// A shared prefix catches the half-typed group name, `ticker:` for
			// `ticker:run`, which the edit distance alone would not.
			if (str_starts_with($candidate, $name) || str_starts_with($name, explode(":", $candidate)[0] . ":")) {
				$suggestions[$candidate] = 0;
				continue;
			}

			$distance = levenshtein($name, $candidate);

			if ($distance <= 3)
				$suggestions[$candidate] = $distance;
		}

		asort($suggestions);
		return array_slice(array_keys($suggestions), 0, 5);
	}
}
