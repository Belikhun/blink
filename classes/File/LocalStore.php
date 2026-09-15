<?php

namespace Blink\File;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Blink\File\Exception\FileStoreWriteError;

/**
 * Content on the local disk, nested under the first characters of its hash.
 *
 * ```
 * data/files/ab/cd/abcd1e2f...
 * ```
 *
 * The nesting is the point. A hash is evenly distributed by construction, so
 * two levels of two hex characters spread content across 65,536 directories
 * without anything having to decide where a file belongs. Everything in one
 * directory, which is what blink did before, turns every lookup into a scan
 * of a directory holding every file the site has ever taken.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LocalStore implements Store {
	/**
	 * Characters of the hash each directory level takes.
	 */
	const SEGMENT = 2;

	protected string $root;

	protected int $depth;

	public function __construct(?string $root = null, ?int $depth = null) {
		$this -> root = rtrim(str_replace("\\", "/", $root ?? \CONFIG::$FILES_ROOT), "/");
		$this -> depth = max(0, $depth ?? \CONFIG::$FILE_SHARD_DEPTH);
	}

	public function name(): string {
		return "local";
	}

	public function root(): string {
		return $this -> root;
	}

	/**
	 * Where content with this hash lives.
	 */
	public function path(string $hash): ?string {
		$hash = static::clean($hash);
		$parts = array( $this -> root );

		for ($level = 0; $level < $this -> depth; $level++)
			$parts[] = substr($hash, $level * static::SEGMENT, static::SEGMENT);

		$parts[] = $hash;
		return implode("/", $parts);
	}

	public function exists(string $hash): bool {
		return is_file($this -> path($hash));
	}

	public function put(string $hash, string $source, bool $move = false): void {
		$target = $this -> path($hash);

		// Already held. The bytes are the same by definition, and rewriting
		// them would only risk truncating what another row is reading.
		if (is_file($target)) {
			if ($move)
				@unlink($source);

			return;
		}

		$directory = dirname($target);

		if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory))
			throw new FileStoreWriteError($hash, "could not create {$directory}");

		// Staged beside the target and renamed into place, so a reader never
		// opens a file that is still being written.
		$staged = @tempnam($directory, ".blink-");

		if ($staged === false)
			throw new FileStoreWriteError($hash, "could not stage content in {$directory}");

		$copied = $move
			? @rename($source, $staged)
			: @copy($source, $staged);

		if (!$copied) {
			@unlink($staged);
			throw new FileStoreWriteError($hash, "could not read {$source}");
		}

		if (!@rename($staged, $target)) {
			@unlink($staged);
			throw new FileStoreWriteError($hash, "could not move content into place");
		}

		@chmod($target, \CONFIG::$FILE_PERMISSIONS);
	}

	public function get(string $hash): ?string {
		$path = $this -> path($hash);

		if (!is_file($path))
			return null;

		$content = @file_get_contents($path);

		return ($content === false)
			? null
			: $content;
	}

	public function stream(string $hash) {
		$path = $this -> path($hash);

		if (!is_file($path))
			return null;

		return @fopen($path, "rb") ?: null;
	}

	public function size(string $hash): int {
		$path = $this -> path($hash);

		if (!is_file($path))
			return -1;

		$size = @filesize($path);

		return ($size === false)
			? -1
			: $size;
	}

	public function forget(string $hash): bool {
		$path = $this -> path($hash);

		if (!is_file($path))
			return false;

		if (!@unlink($path))
			return false;

		$this -> pruneDirectories(dirname($path));
		return true;
	}

	/**
	 * Walk the shard directories, yielding every hash held.
	 *
	 * @return \Generator<string>
	 */
	public function hashes(): iterable {
		if (!is_dir($this -> root))
			return;

		$entries = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this -> root, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($entries as $entry) {
			if (!$entry -> isFile())
				continue;

			$name = $entry -> getFilename();

			// A staging file from a write still in flight, or something that
			// was never content to begin with.
			if (str_starts_with($name, ".") || !preg_match('/^[0-9a-f]{8,128}$/', $name))
				continue;

			yield $name;
		}
	}

	/**
	 * Remove shard directories left empty, up to the root.
	 *
	 * A site that churns through files would otherwise accumulate tens of
	 * thousands of empty directories it never looks at again.
	 */
	protected function pruneDirectories(string $directory): void {
		for ($level = 0; $level < $this -> depth; $level++) {
			if ($directory === $this -> root || !is_dir($directory))
				return;

			// Fails while anything is still in there, which is the test.
			if (!@rmdir($directory))
				return;

			$directory = dirname($directory);
		}
	}

	/**
	 * Keep a hash to the characters a path may safely be built from.
	 */
	protected static function clean(string $hash): string {
		$hash = strtolower(trim($hash));
		$clean = preg_replace('/[^0-9a-z]/', "", $hash) ?? "";

		return ($clean === "")
			? "invalid"
			: $clean;
	}
}
