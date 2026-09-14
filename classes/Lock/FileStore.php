<?php

namespace Blink\Lock;

use Blink\Exception\FileLockError;

/**
 * Locks kept as files under the data directory.
 *
 * Only reaches the local filesystem, so it covers processes on one host and no
 * further. Terra uses it because the table the database store needs is itself
 * created by a layer.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileStore extends Store {
	protected string $root;

	public function __construct(string $root = null) {
		$this -> root = $root ?? (DATA_ROOT . "/locks");

		if (!file_exists($this -> root))
			mkdir($this -> root, recursive: true);
	}

	public function acquire(string $name, string $token, int $ttl, string $owner): bool {
		$now = time();

		return $this -> withFile($name, function ($handle) use ($name, $token, $ttl, $owner, $now) {
			$current = $this -> readHandle($handle);

			if (!empty($current) && $current["expires"] > $now)
				return false;

			$this -> writeHandle($handle, array(
				"name" => $name,
				"token" => $token,
				"owner" => $owner,
				"acquired" => $now,
				"expires" => $now + $ttl
			));

			return true;
		});
	}

	public function release(string $name, string $token): bool {
		return $this -> withFile($name, function ($handle) use ($token) {
			$current = $this -> readHandle($handle);

			if (empty($current) || $current["token"] !== $token)
				return false;

			ftruncate($handle, 0);
			return true;
		});
	}

	public function refresh(string $name, string $token, int $ttl): bool {
		return $this -> withFile($name, function ($handle) use ($token, $ttl) {
			$current = $this -> readHandle($handle);

			if (empty($current) || $current["token"] !== $token)
				return false;

			$current["expires"] = time() + $ttl;
			$this -> writeHandle($handle, $current);
			return true;
		});
	}

	public function holder(string $name): ?array {
		$path = $this -> path($name);

		if (!file_exists($path))
			return null;

		$current = $this -> decode((string) file_get_contents($path));

		return (!empty($current) && $current["expires"] > time())
			? $current
			: null;
	}

	public function all(): array {
		$locks = array();

		foreach (glob($this -> root . "/*.lock") as $path) {
			$current = $this -> decode((string) file_get_contents($path));

			if (!empty($current) && $current["expires"] > time())
				$locks[$current["name"]] = $current;
		}

		return $locks;
	}

	public function forget(string $name): bool {
		$path = $this -> path($name);

		if (!file_exists($path))
			return false;

		return unlink($path);
	}

	/**
	 * Run a read-modify-write against a lock file, holding an exclusive
	 * `flock` for the whole of it.
	 *
	 * The flock only guards the file's contents against two processes
	 * interleaving. The lock itself is what the contents say, which is why it
	 * survives this function returning.
	 */
	protected function withFile(string $name, callable $handler): bool {
		$path = $this -> path($name);
		$handle = fopen($path, "c+");

		if ($handle === false)
			throw new FileLockError($path, "could not be opened");

		try {
			if (!flock($handle, LOCK_EX))
				throw new FileLockError($path, "could not be locked");

			$result = $handler($handle);

			fflush($handle);
			flock($handle, LOCK_UN);
			return (bool) $result;
		} finally {
			fclose($handle);
		}
	}

	protected function readHandle($handle): ?array {
		rewind($handle);
		$content = stream_get_contents($handle);

		return $this -> decode($content === false ? "" : $content);
	}

	protected function writeHandle($handle, array $lock): void {
		rewind($handle);
		ftruncate($handle, 0);
		fwrite($handle, json_encode($lock));
	}

	protected function decode(string $content): ?array {
		$content = trim($content);

		if ($content === "")
			return null;

		$decoded = json_decode($content, true);

		return is_array($decoded) && isset($decoded["expires"])
			? $decoded
			: null;
	}

	/**
	 * A lock name is caller-supplied and can carry anything, so the file name
	 * is a hash with a readable prefix rather than the name itself.
	 */
	protected function path(string $name): string {
		$slug = preg_replace("/[^a-z0-9]+/i", "-", $name);
		$slug = trim(strtolower((string) $slug), "-");

		return $this -> root . "/" . substr($slug, 0, 40) . "-" . substr(sha1($name), 0, 8) . ".lock";
	}
}
