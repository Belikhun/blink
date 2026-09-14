<?php

namespace Blink\Console\Input;

/**
 * One positional argument in a command's usage line.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Argument {
	public string $name;

	public bool $required;

	/**
	 * Whether this argument swallows every remaining token, which only the
	 * last argument can do.
	 */
	public bool $variadic;

	public mixed $default;

	public ?string $description;

	public function __construct(
		string $name,
		bool $required = true,
		bool $variadic = false,
		mixed $default = null,
		string $description = null
	) {
		$this -> name = $name;
		$this -> required = $required;
		$this -> variadic = $variadic;
		$this -> default = $default;
		$this -> description = $description;
	}

	/**
	 * How the argument reads back in a usage line.
	 */
	public function synopsis(): string {
		$name = $this -> variadic
			? "{$this -> name}..."
			: $this -> name;

		return $this -> required
			? "<{$name}>"
			: "[{$name}]";
	}
}
