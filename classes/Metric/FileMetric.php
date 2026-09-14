<?php

namespace Blink\Metric;

use Blink\Metric;
use function Blink\convertSize;
use function Blink\getRelativePath;

/**
 * One file read or written.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileMetric extends MetricInstance {
	public string $mode;

	public string $type;

	public string $file;

	public int $size = -1;

	public function __construct(string $mode, string $type, string $file) {
		parent::__construct();

		// A cache file's name is a hash nobody will recognize, so show only
		// enough of it to tell two apart.
		if (str_ends_with($file, ".cache")) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			$file = "[cache] " . substr($name, 0, 8);
		}

		$this -> mode = $mode;
		$this -> type = $type;
		$this -> file = $file;

		Metric::$files[] = $this;
	}

	public function time(int $size): static {
		$this -> size = $size;
		$this -> time = microtime(true);
		return $this;
	}

	public function save(): ?array {
		$file = getRelativePath($this -> file);

		return array(
			"hash" => substr(md5($file), 0, 12),
			"file" => $file,
			"mode" => $this -> mode,
			"type" => $this -> type,
			"size" => $this -> size,
			"delta_start" => $this -> deltaStart,
			"duration" => $this -> getTime()
		);
	}

	public function __toString(): string {
		return sprintf("%5s %3s %10s %12s %s",
			$this -> timeFormat(),
			$this -> mode,
			$this -> type,
			convertSize($this -> size),
			getRelativePath($this -> file));
	}
}
