<?php

namespace Blink\Console\Input;

/**
 * One `--option` in a command's usage line.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Option {
	public string $name;

	/**
	 * Single-letter form, or null when the option has only a long name.
	 */
	public ?string $short;

	/**
	 * Whether the option carries a value. One that does not is a flag, and
	 * reads back as a boolean.
	 */
	public bool $takesValue;

	public mixed $default;

	public ?string $description;

	public function __construct(
		string $name,
		string $short = null,
		bool $takesValue = false,
		mixed $default = null,
		string $description = null
	) {
		$this -> name = $name;
		$this -> short = $short;
		$this -> takesValue = $takesValue;
		$this -> default = $default;
		$this -> description = $description;
	}

	public function synopsis(): string {
		$flag = !empty($this -> short)
			? "-{$this -> short}, --{$this -> name}"
			: "--{$this -> name}";

		return $this -> takesValue
			? "{$flag}=<value>"
			: $flag;
	}
}
