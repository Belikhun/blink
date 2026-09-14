<?php

namespace Blink\Console\Input;

use Blink\Exception\CodingError;

/**
 * A command's signature, read from its usage line.
 *
 * The usage line names the command and everything it takes, in one place a
 * reader can take in at a glance:
 *
 * ```
 * ticker:run {queue?} {--once} {--m|memory=128 : Memory ceiling in MB}
 * ```
 *
 * Arguments are positional; anything starting `--` is an option. A `?` makes
 * an argument optional, `=` gives it a default, `*` lets the last one swallow
 * what remains, and text after a colon is the description shown in help.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Spec {
	public string $name;

	/** @var Argument[] */
	public array $arguments = array();

	/** @var Option[] */
	public array $options = array();

	public function __construct(string $name) {
		$this -> name = $name;
	}

	public static function parse(string $usage): static {
		$usage = trim($usage);

		if ($usage === "")
			throw new CodingError("A command's <code>\$usage</code> cannot be empty.");

		$matches = array();
		preg_match_all("/\{\s*(.*?)\s*\}/", $usage, $matches);

		// Everything before the first token is the command name.
		$name = trim((string) strtok($usage, " \t"));
		$name = trim(explode("{", $name)[0]);

		if ($name === "")
			throw new CodingError("A command's <code>\$usage</code> has to start with the command name.");

		$spec = new static($name);

		foreach ($matches[1] as $token)
			$spec -> add($token);

		return $spec;
	}

	public function argument(string $name): ?Argument {
		foreach ($this -> arguments as $argument) {
			if ($argument -> name === $name)
				return $argument;
		}

		return null;
	}

	/**
	 * Find an option by either of its names.
	 */
	public function option(string $name): ?Option {
		foreach ($this -> options as $option) {
			if ($option -> name === $name || $option -> short === $name)
				return $option;
		}

		return null;
	}

	/**
	 * The command line as it reads in help.
	 */
	public function synopsis(): string {
		$parts = array( $this -> name );

		foreach ($this -> arguments as $argument)
			$parts[] = $argument -> synopsis();

		if (!empty($this -> options))
			$parts[] = "[options]";

		return implode(" ", $parts);
	}

	protected function add(string $token): void {
		$description = null;

		// The description is separated by a spaced colon, so a default value
		// containing a colon (a URL, a time) stays intact.
		if (str_contains($token, " : ")) {
			list($token, $description) = explode(" : ", $token, 2);
			$token = trim($token);
			$description = trim($description);
		}

		if (str_starts_with($token, "--")) {
			$this -> options[] = $this -> parseOption(substr($token, 2), $description);
			return;
		}

		$this -> arguments[] = $this -> parseArgument($token, $description);
	}

	protected function parseArgument(string $token, ?string $description): Argument {
		$default = null;
		$required = true;
		$variadic = false;

		if (str_contains($token, "=")) {
			list($token, $default) = explode("=", $token, 2);
			$required = false;
		}

		if (str_ends_with($token, "*")) {
			$token = substr($token, 0, -1);
			$variadic = true;
			$default = $default ?? array();
		}

		if (str_ends_with($token, "?")) {
			$token = substr($token, 0, -1);
			$required = false;
		}

		// `{name*?}` is the same thing written the other way round.
		if (str_ends_with($token, "*")) {
			$token = substr($token, 0, -1);
			$variadic = true;
		}

		$name = trim($token);

		if ($name === "")
			throw new CodingError("A command argument has no name.");

		if ($variadic) {
			foreach ($this -> arguments as $existing) {
				if ($existing -> variadic)
					throw new CodingError("Only one command argument can be variadic.");
			}
		}

		return new Argument($name, $required, $variadic, $default, $description);
	}

	protected function parseOption(string $token, ?string $description): Option {
		$short = null;
		$default = null;
		$takesValue = false;

		if (str_contains($token, "=")) {
			list($token, $default) = explode("=", $token, 2);
			$takesValue = true;

			// `{--opt=}` asks for a value without suggesting one.
			if ($default === "")
				$default = null;
		}

		if (str_contains($token, "|")) {
			list($short, $token) = explode("|", $token, 2);
			$short = trim($short);
		}

		$name = trim($token);

		if ($name === "")
			throw new CodingError("A command option has no name.");

		return new Option($name, $short, $takesValue, $default, $description);
	}
}
