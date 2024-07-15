<?php

namespace Blink\Response;

/**
 * PartedFileResponse.php
 * 
 * Serve a file, but with Content-Range support.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class PartedFileResponse extends FileResponse {
	public function process(): string {
		$fs = @fopen($this -> path, "rb");

		$size   = filesize($this -> path);
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte

		$this -> header("Content-Type", $this -> mimetype);
		$this -> header("Accept-Ranges", "0-$length");

		if ($this -> download)
			$this -> header("Content-Disposition", "attachment; filename=\"{$this -> filename}\"");

		if (isset($_SERVER["HTTP_RANGE"])) {
			$c_start = $start;
			$c_end   = $end;

			list(, $range) = explode("=", $_SERVER["HTTP_RANGE"], 2);

			if (strpos($range, ",") !== false) {
				$this -> status(416);
				$this -> header("Content-Range", "bytes $start-$end/$size");
				return "NO";
			}

			if ($range == "-") {
				$c_start = $size - substr($range, 1);
			} else {
				$range   = explode("-", $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}

			$c_end = ($c_end > $end) ? $end : $c_end;

			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
				$this -> status(416);
				$this -> header("Content-Range", "bytes $start-$end/$size");
				return "NO";
			}

			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1;
			$this -> status(206);
		}

		$this -> header("Content-Range", "bytes $start-$end/$size");
		$this -> header("Content-Length", $length);

		$this -> fs = $fs;
		$this -> start = $start;
		$this -> end = $end;
		return "OK";
	}

	public function serve(): string {
		$signal = parent::serve();

		if ($signal !== "OK")
			return "";

		// Clean the output to make sure we write file data
		// directly to output.
		while (ob_get_level())
			ob_end_clean();

		set_time_limit(0);
		$buffer = static::BUFFER;
		fseek($this -> fs, $this -> start);

		while (!feof($this -> fs) && ($p = ftell($this -> fs)) <= $this -> end) {
			if ($p + $buffer > $this -> end)
				$buffer = $this -> end - $p + 1;

			echo fread($this -> fs, $buffer);
			flush();
		}

		fclose($this -> fs);
		die();
	}
}
