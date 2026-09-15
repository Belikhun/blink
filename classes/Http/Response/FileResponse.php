<?php

namespace Blink\Http\Response;

use Blink\Exception\FileNotFound;
use Blink\File;
use Blink\Http\Response;
use function Blink\mime;

/**
 * Serve a file, either from the file store or from a path on disk.
 *
 * A store that keeps content somewhere other than the local filesystem has no
 * path to hand over, so everything here goes through a stream and the path is
 * only an optimization for the store that does.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileResponse extends Response {
	const BUFFER = 1024 * 8;

	/**
	 * A local path for the content, when there is one.
	 */
	protected ?string $path = null;

	protected ?File $instance = null;

	protected string $filename;

	protected bool $download = false;

	protected string $mimetype = "application/octet-stream";

	protected int $length = 0;

	protected $fs;
	protected $start;
	protected $end;

	public function __construct(File|string $file, bool $download = false) {
		$this -> file($file);
		$this -> download = $download;
	}

	public function file(File|string $file) {
		if (is_string($file)) {
			if (!file_exists($file))
				throw new FileNotFound($file);

			$this -> instance = null;
			$this -> path = $file;
			$this -> mimetype = mime($file);
			$this -> filename = pathinfo($file, PATHINFO_BASENAME);
			$this -> length = (int) (@filesize($file) ?: 0);

			return $this;
		}

		$this -> instance = $file;
		$this -> mimetype = $file -> mimetype;
		$this -> filename = $file -> filename;
		$this -> path = $file -> path();
		$this -> length = $file -> size;

		// A local store answers this cheaply, and a remote one cannot answer
		// it at all without fetching, so the row's own size stands in.
		if ($this -> path !== null && !file_exists($this -> path))
			throw new FileNotFound($this -> path);

		return $this;
	}

	/**
	 * A read handle on the content. The caller closes it.
	 *
	 * @return	resource
	 */
	protected function handle() {
		if ($this -> instance !== null && $this -> path === null)
			return $this -> instance -> stream();

		$stream = @fopen((string) $this -> path, "rb");

		if ($stream === false)
			throw new FileNotFound((string) $this -> path);

		return $stream;
	}

	public function process(): string {
		$this -> header("Content-Type", $this -> mimetype);
		$this -> header("Content-Length", $this -> length);

		if ($this -> download)
			$this -> header("Content-Disposition", "attachment; filename=\"{$this -> filename}\"");

		if ($this -> path !== null)
			return (string) @file_get_contents($this -> path);

		$stream = $this -> handle();

		try {
			return (string) stream_get_contents($stream);
		} finally {
			fclose($stream);
		}
	}
}
