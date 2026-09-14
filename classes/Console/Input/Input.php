<?php

namespace Blink\Console\Input;

use Blink\Console\Exception\InvalidInput;

/**
 * The tokens a command was invoked with, matched against its {@see Spec}.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Input {
	protected Spec $spec;

	/** @var array<string, mixed> */
	protected array $arguments = array();

	/** @var array<string, mixed> */
	protected array $options = array();

	/**
	 * Options the caller actually passed, as opposed to ones sitting at their
	 * default. `--force` and a default of false read the same otherwise.
	 *
	 * @var string[]
	 */
	protected array $supplied = array();

	public function __construct(Spec $spec) {
		$this -> spec = $spec;
	}

	/**
	 * @param	Spec		$spec
	 * @param	string[]	$tokens		Everything after the command name.
	 */
	public static function parse(Spec $spec, array $tokens): static {
		$input = new static($spec);
		$positional = array();
		$optionsClosed = false;

		for ($index = 0; $index < count($tokens); $index++) {
			$token = $tokens[$index];

			// A bare `--` means everything after it is positional, which is
			// how a value starting with a dash gets through.
			if (!$optionsClosed && $token === "--") {
				$optionsClosed = true;
				continue;
			}

			if (!$optionsClosed && str_starts_with($token, "--")) {
				$input -> consumeOption($spec, substr($token, 2), $tokens, $index);
				continue;
			}

			if (!$optionsClosed && strlen($token) > 1 && $token[0] === "-") {
				$input -> consumeOption($spec, substr($token, 1), $tokens, $index, short: true);
				continue;
			}

			$positional[] = $token;
		}

		$input -> fillArguments($spec, $positional);
		$input -> fillOptionDefaults($spec);

		return $input;
	}

	public function spec(): Spec {
		return $this -> spec;
	}

	/**
	 * One argument's value, or every argument when called with no name.
	 */
	public function argument(string $name = null): mixed {
		if ($name === null)
			return $this -> arguments;

		if (!array_key_exists($name, $this -> arguments))
			throw new InvalidInput("Unknown argument <{$name}>.");

		return $this -> arguments[$name];
	}

	/**
	 * @return	array<string, mixed>
	 */
	public function arguments(): array {
		return $this -> arguments;
	}

	/**
	 * One option's value, or every option when called with no name.
	 */
	public function option(string $name = null): mixed {
		if ($name === null)
			return $this -> options;

		if (!array_key_exists($name, $this -> options))
			throw new InvalidInput("Unknown option --{$name}.");

		return $this -> options[$name];
	}

	/**
	 * @return	array<string, mixed>
	 */
	public function options(): array {
		return $this -> options;
	}

	/**
	 * Whether the caller passed this option, rather than it holding a default.
	 */
	public function supplied(string $name): bool {
		return in_array($name, $this -> supplied, true);
	}

	/**
	 * @param	string[]	$tokens
	 */
	protected function consumeOption(Spec $spec, string $token, array $tokens, int &$index, bool $short = false): void {
		$value = null;
		$hasInlineValue = false;

		if (str_contains($token, "=")) {
			list($token, $value) = explode("=", $token, 2);
			$hasInlineValue = true;
		}

		$option = $spec -> option($token);

		if ($option === null) {
			$flag = $short ? "-{$token}" : "--{$token}";
			throw new InvalidInput("Unknown option {$flag}.");
		}

		if (!$option -> takesValue) {
			if ($hasInlineValue)
				throw new InvalidInput("Option --{$option -> name} does not take a value.");

			$this -> options[$option -> name] = true;
			$this -> supplied[] = $option -> name;
			return;
		}

		if (!$hasInlineValue) {
			// The value is the next token, unless there is not one or it is
			// plainly another option.
			$next = $tokens[$index + 1] ?? null;

			if ($next === null || (strlen($next) > 1 && $next[0] === "-"))
				throw new InvalidInput("Option --{$option -> name} needs a value.");

			$value = $next;
			$index++;
		}

		$this -> options[$option -> name] = $value;
		$this -> supplied[] = $option -> name;
	}

	/**
	 * @param	string[]	$positional
	 */
	protected function fillArguments(Spec $spec, array $positional): void {
		foreach ($spec -> arguments as $argument) {
			if ($argument -> variadic) {
				$this -> arguments[$argument -> name] = $positional;
				$positional = array();

				if ($argument -> required && empty($this -> arguments[$argument -> name]))
					throw new InvalidInput("Argument <{$argument -> name}> is required.");

				continue;
			}

			if (empty($positional)) {
				if ($argument -> required)
					throw new InvalidInput("Argument <{$argument -> name}> is required.");

				$this -> arguments[$argument -> name] = $argument -> default;
				continue;
			}

			$this -> arguments[$argument -> name] = array_shift($positional);
		}

		if (!empty($positional))
			throw new InvalidInput("Unexpected argument \"" . $positional[0] . "\".");
	}

	protected function fillOptionDefaults(Spec $spec): void {
		foreach ($spec -> options as $option) {
			if (array_key_exists($option -> name, $this -> options))
				continue;

			$this -> options[$option -> name] = $option -> takesValue
				? $option -> default
				: false;
		}
	}
}
