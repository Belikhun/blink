<?php

namespace Blink;

/**
 * Represent a execution frame.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class BacktraceFrame {
	/**
	 * Path to frame file.
	 * 
	 * @var ?string
	 */
	public ?string $file = null;

	/**
	 * Line in the file of the trace.
	 * 
	 * @var ?string
	 */
	public int $line = -1;

	public ?string $class = null;

	public ?string $type = null;

	public string $function;

	public array $args = array();

	public bool $fault = false;


	private ?string $id = null;

	private ?string $fullpath = null;

	private ?string $hash = null;


	public function __construct(string $function = "[unknown]", bool $fault = false) {
		$this -> function = $function;
		$this -> fault = $fault;
	}

	public function getFullPath() {
		if (empty($this -> file))
			return null;

		if (empty($this -> fullpath)) {
			$this -> fullpath = (!empty($this -> file) && $this -> file[0] === "/" && !str_starts_with($this -> file, BASE_PATH))
				? BASE_PATH . $this -> file
				: $this -> file;
		}

		return $this -> fullpath;
	}

	public function getCallString() {
		if (!empty($this -> class)) {
			$string = $this -> class;
			$string .= ($this -> type === "::")
				? "::{$this -> function}"
				: " -> {$this -> function}";

			return $string;
		}

		return $this -> function;
	}

	/**
	 * Generate a random id for this frame and return it.
	 * @return	string
	 */
	public function getID(): string {
		if (empty($this -> id))
			$this -> id = randString(8, RAND_CHARSET_HEX);

		return $this -> id;
	}

	public function hash(): string {
		if (!empty($this -> hash))
			return $this -> hash;

		$this -> hash = md5(($this -> file ?: "file") . ($this -> line ?: "0"));
		return $this -> hash;
	}

	public function isBlink() {
		if (!empty($this -> file))
			return str_starts_with($this -> getFullPath(), CORE_ROOT);

		if (!empty($this -> class))
			return str_starts_with($this -> class, "Blink");

		return false;
	}

	public function isVendor() {
		if (!empty($this -> file))
			return str_starts_with($this -> getFullPath(), VENDOR_ROOT);

		return false;
	}

	public function __serialize() {
		return array(
			"file" => $this -> file,
			"line" => $this -> line,
			"class" => $this -> class,
			"type" => $this -> type,
			"function" => $this -> function,
			"args" => $this -> args,
			"fault" => $this -> fault
		);
	}

	public function __unserialize(array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}
