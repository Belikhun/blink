<?php

namespace Blink\Console;

/**
 * Writing to the terminal.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Output {
	const COLORS = array(
		"reset" => "0",
		"bold" => "1",
		"dim" => "2",
		"red" => "31",
		"green" => "32",
		"yellow" => "33",
		"blue" => "34",
		"magenta" => "35",
		"cyan" => "36",
		"white" => "37",
		"gray" => "90"
	);

	/**
	 * Whether colour is worth emitting. Answered once, since it cannot change
	 * within a run.
	 */
	protected ?bool $decorated = null;

	/** @var resource */
	protected $stream;

	/** @var resource */
	protected $errorStream;

	public function __construct($stream = null, $errorStream = null) {
		$this -> stream = $stream ?? (defined("STDOUT") ? STDOUT : fopen("php://stdout", "w"));
		$this -> errorStream = $errorStream ?? (defined("STDERR") ? STDERR : fopen("php://stderr", "w"));
	}

	public function write(string $text): void {
		fwrite($this -> stream, $text);
	}

	public function line(string $text = ""): void {
		$this -> write($text . PHP_EOL);
	}

	public function info(string $text): void {
		$this -> line($this -> paint($text, "cyan"));
	}

	public function success(string $text): void {
		$this -> line($this -> paint($text, "green"));
	}

	public function warning(string $text): void {
		$this -> line($this -> paint($text, "yellow"));
	}

	public function comment(string $text): void {
		$this -> line($this -> paint($text, "gray"));
	}

	/**
	 * Errors go to stderr, so a command's real output stays pipeable.
	 */
	public function error(string $text): void {
		fwrite($this -> errorStream, $this -> paint($text, "red") . PHP_EOL);
	}

	/**
	 * A heading with a rule under it.
	 */
	public function heading(string $text): void {
		$this -> line();
		$this -> line($this -> paint($text, "bold"));
		$this -> line($this -> paint(str_repeat("-", mb_strlen($text)), "gray"));
	}

	/**
	 * Render rows in aligned columns.
	 *
	 * @param	string[]		$headers
	 * @param	array<array>	$rows
	 */
	public function table(array $headers, array $rows): void {
		$widths = array();

		foreach ($headers as $index => $header)
			$widths[$index] = mb_strlen((string) $header);

		foreach ($rows as $row) {
			foreach (array_values($row) as $index => $cell)
				$widths[$index] = max($widths[$index] ?? 0, mb_strlen((string) $cell));
		}

		$render = function (array $cells, string $color = null) use ($widths) {
			$parts = array();

			foreach (array_values($cells) as $index => $cell) {
				$cell = (string) $cell;
				$parts[] = $cell . str_repeat(" ", max($widths[$index] - mb_strlen($cell), 0));
			}

			$line = rtrim(implode("  ", $parts));
			$this -> line($color ? $this -> paint($line, $color) : $line);
		};

		if (!empty($headers)) {
			$render($headers, "bold");
			$render(array_map(fn ($width) => str_repeat("-", $width), $widths), "gray");
		}

		foreach ($rows as $row)
			$render($row);
	}

	/**
	 * Ask a yes or no question. Answers itself with the default when there is
	 * nobody at the keyboard, so a command in a script never hangs.
	 */
	public function confirm(string $question, bool $default = false): bool {
		$hint = $default ? "[Y/n]" : "[y/N]";
		$this -> write("{$question} {$hint} ");

		$answer = defined("STDIN") ? fgets(STDIN) : false;

		if ($answer === false) {
			$this -> line($default ? "y" : "n");
			return $default;
		}

		$answer = strtolower(trim($answer));

		if ($answer === "")
			return $default;

		return in_array($answer, array( "y", "yes" ), true);
	}

	/**
	 * Wrap text in an ANSI colour, or hand it back untouched where colour
	 * would only turn into noise.
	 */
	public function paint(string $text, string $color): string {
		if (!$this -> decorated() || !isset(static::COLORS[$color]))
			return $text;

		return "\033[" . static::COLORS[$color] . "m{$text}\033[0m";
	}

	public function decorated(): bool {
		if ($this -> decorated !== null)
			return $this -> decorated;

		// A redirected stream is being read by something, not someone.
		if (!stream_isatty($this -> stream))
			return $this -> decorated = false;

		// Windows consoles need the escape sequences switched on, and older
		// ones cannot do it at all.
		if (DIRECTORY_SEPARATOR === "\\" && function_exists("sapi_windows_vt100_support"))
			return $this -> decorated = sapi_windows_vt100_support($this -> stream, true);

		return $this -> decorated = true;
	}

	/**
	 * Force colour on or off, for a caller that knows better than the guess.
	 */
	public function decorate(bool $decorated): void {
		$this -> decorated = $decorated;
	}
}
