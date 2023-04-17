<?php
/**
 * FileResponse.php
 * 
 * Serve a file.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Response;
use Blink\Exception\FileNotFound;
use Blink\File;
use Blink\Response;

class FileResponse extends Response {
	const BUFFER = 1024 * 8;

	protected String $path;
	protected String $filename;
	protected bool $download = false;
	protected String $mimetype = "application/octet-stream";

	private $fs;
	private $start;
	private $end;

	public function __construct(File|String $file, bool $download = false) {
		$this -> file($file);
		$this -> download = $download;
	}

	public function file(File|String $file) {
		if (is_string($file)) {
			if (!file_exists($file))
				throw new FileNotFound($file);

			$this -> mimetype = mime($file);
			$this -> path = $file;
			$this -> filename = pathinfo($file, PATHINFO_FILENAME);
		} else {
			$this -> mimetype = $file -> mimetype;
			$this -> path = $file -> getStorePath();
			$this -> filename = $file -> filename;

			if (!file_exists($this -> path))
				throw new FileNotFound($this -> path);
		}

		return $this;
	}

	public function process(): String {
		$length = filesize($this -> path);
		$this -> header("Content-Type", $this -> mimetype);
		$this -> header("Content-Length", $length);
		return file_get_contents($this -> path);
	}
}
