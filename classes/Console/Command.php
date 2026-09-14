<?php

namespace Blink\Console;

use Blink\Console\Input\Input;
use Blink\Console\Input\Spec;

/**
 * One console command.
 *
 * A command declares what it takes in a usage line and does its work in
 * `run()`:
 *
 * ```php
 * class PurgeCommand extends Command {
 * 	public static string $usage = "cache:purge {--force : Skip the prompt}";
 * 	public static string $description = "Empty the cache directory";
 *
 * 	public function run(Cache $cache) {
 * 		if (!$this -> option("force") && !$this -> confirm("Purge the cache?"))
 * 			return Console::EXIT_OK;
 *
 * 		$cache -> clear();
 * 		$this -> success("Done.");
 * 	}
 * }
 * ```
 *
 * `run()` is called through Essence, so it can ask for whatever services it
 * needs by type-hinting them.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class Command {
	/**
	 * The command name followed by what it takes. See {@see Spec} for the
	 * shape of it.
	 *
	 * Static so `blink list` can read every command's signature without
	 * constructing one, which would mean running constructors that expect a
	 * database the listing has no reason to touch.
	 */
	public static string $usage = "";

	/**
	 * One line saying what the command does, shown in the listing.
	 */
	public static string $description = "";

	/**
	 * Keep the command out of the listing. For one that exists to be called by
	 * another command rather than by a person.
	 */
	public static bool $hidden = false;

	protected Input $input;

	protected Output $output;

	/**
	 * Do the work.
	 *
	 * Return one of the `Console::EXIT_*` codes, or nothing at all, which
	 * counts as success.
	 *
	 * @return	?int
	 */
	abstract public function run();

	public static function spec(): Spec {
		return Spec::parse(static::$usage);
	}

	public static function name(): string {
		return static::spec() -> name;
	}

	public function bind(Input $input, Output $output): void {
		$this -> input = $input;
		$this -> output = $output;
	}

	public function input(): Input {
		return $this -> input;
	}

	public function output(): Output {
		return $this -> output;
	}

	protected function argument(string $name = null): mixed {
		return $this -> input -> argument($name);
	}

	protected function option(string $name = null): mixed {
		return $this -> input -> option($name);
	}

	protected function line(string $text = ""): void {
		$this -> output -> line($text);
	}

	protected function info(string $text): void {
		$this -> output -> info($text);
	}

	protected function success(string $text): void {
		$this -> output -> success($text);
	}

	protected function warning(string $text): void {
		$this -> output -> warning($text);
	}

	protected function comment(string $text): void {
		$this -> output -> comment($text);
	}

	protected function error(string $text): void {
		$this -> output -> error($text);
	}

	protected function heading(string $text): void {
		$this -> output -> heading($text);
	}

	/**
	 * @param	string[]		$headers
	 * @param	array<array>	$rows
	 */
	protected function table(array $headers, array $rows): void {
		$this -> output -> table($headers, $rows);
	}

	protected function confirm(string $question, bool $default = false): bool {
		return $this -> output -> confirm($question, $default);
	}
}
