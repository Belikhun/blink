<?php
/**
 * File.php
 * 
 * File Metric
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Metric;

class File extends Instance {
	public String $mode;
	public String $type;
	public String $file;
	public int $size;

	public function __construct(String $mode, String $type, String $file) {
		// Cache file is treated differently.
		if (str_ends_with($file, ".cache")) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			$file = "[cache] " . substr($name, 0, 8);
		}

		$this -> mode = $mode;
		$this -> type = $type;
		$this -> file = $file;
		$this -> time = -1;
		$this -> size = -1;
		$this -> start = microtime(true);
		\Blink\Metric::$files[] = $this;
	}

	public function time(int $size) {
		$this -> size = $size;
		$this -> time = microtime(true);
	}

	public function __toString() {
		return sprintf("%5s %3s %10s %12s %s",
			$this -> timeFormat(),
			$this -> mode,
			$this -> type,
			convertSize($this -> size),
			getRelativePath($this -> file));
	}
}