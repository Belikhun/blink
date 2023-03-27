<?php

/**
 * BacktraceFrame.php
 * 
 * Represent a execution frame.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2022 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

class BacktraceFrame {
	public ?String $file = null;
	public int $line = -1;
	public ?String $class = null;
	public ?String $type = null;
	public String $function;
	public Array $args = Array();
	private ?String $id = null;

	public function __construct(String $function = "[unknown]") {
		$this -> function = $function;
	}

	public function getFullPath() {
		return (!empty($this -> file) && $this -> file[0] === "/" && !str_starts_with($this -> file, BASE_PATH))
			? BASE_PATH . $this -> file
			: $this -> file;
	}

	public function getCallString() {
		if (!empty($this -> class)) {
			$string = $this -> class;
			$string .= ($this -> type === "::")
				? "::{$this -> function}"
				: " -> {$this -> function}";
		}

		return $this -> function;
	}

	/**
	 * Generate a random id for this frame and return it.
	 * @return	string
	 */
	public function getID(): String {
		if (empty($this -> id))
			$this -> id = randString(8, RAND_CHARSET_HEX);

		return $this -> id;
	}

	public function __serialize() {
		return Array(
			"file" => $this -> file,
			"line" => $this -> line,
			"class" => $this -> class,
			"type" => $this -> type,
			"function" => $this -> function,
			"args" => $this -> args
		);
	}

	public function __unserialize(array $data) {
		$this -> file = $data["file"];
		$this -> line = $data["line"];
		$this -> class = $data["class"];
		$this -> type = $data["type"];
		$this -> function = $data["function"];
		$this -> args = $data["args"];
	}
}
