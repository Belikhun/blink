<?php

namespace Blink;

use Throwable;

/**
 * Global variable to store created cache instances.
 * @var Cache[]
 */
global $CACHES;
$CACHES = Array();

/**
 * Cache.php
 * 
 * Cache files interface.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Cache {
	const NO_EXPIRE = -1;

	public static String $CACHE_LOCATION;
	public String $id;
	protected Cache\Data $data;
	public String $file;
	public String $path;
	protected FileIO $stream;

	public function __construct($id) {
		$this -> id = $id;
		$this -> file = $this -> id . ".cache";
		$this -> path = static::path($this -> id);
	}

	protected function stream(): FileIO {
		if (empty($this -> stream)) {
			$this -> stream = new FileIO(
				$this -> path,
				FileIO::NO_DEFAULT,
				TYPE_SERIALIZED
			);
		}

		return $this -> stream;
	}

	/**
	 * Try to fetch current data of this cache.
	 * @return bool 
	 */
	public function fetch() {
		if (!file_exists($this -> path))
			return false;

		try {
			$this -> data = $this -> stream() -> read(TYPE_SERIALIZED);
			return true;
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Initialize clean record of this cache.
	 * @return Cache
	 */
	public function initialize() {
		$this -> data = new Cache\Data();
		$this -> data -> id = $this -> id;
		return $this;
	}

	public function initialized() {
		return !empty($this -> data);
	}

	/**
	 * Set cache age.
	 * If set to `Cache::NO_EXPIRE`, this cache will never expire.
	 * 
	 * @param	int		$age
	 */
	public function setAge(int $age) {
		$this -> data -> age = $age;
		return $this;
	}

	/**
	 * Validate Cache Age.
	 * Return `true` if cache lifetime is within set age.
	 * 
	 * @return	bool
	 */
	public function validate() {
		return ($this -> data -> age === Cache::NO_EXPIRE)
			|| (time() - $this -> data -> time) < $this -> data -> age;
	}

	public function data() {
		return $this -> data;
	}

	public function content() {
		return $this -> data -> content;
	}

	public function setContent($content) {
		$this -> data -> content = $content;
		return $this;
	}

	public function save() {
		$this -> data -> time = time();
		$this -> stream() -> write($this -> data, TYPE_SERIALIZED);
		return $this;
	}

	protected static function path(String $id) {
		return static::$CACHE_LOCATION ."/{$id}.cache";
	}

	public function exist(String $id) {
		return file_exists(static::path($id));
	}

	public static function remove(String $id) {
		$path = static::path($id);
		
		if (file_exists($path))
			unlink($path);
	}

	public static function clearAll() {
		$counter = 0;

		if (file_exists(static::$CACHE_LOCATION)) {
			$di = new \RecursiveDirectoryIterator(self::$CACHE_LOCATION, \FilesystemIterator::SKIP_DOTS);
			$ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);

			foreach ($ri as $file) {
				$file -> isDir() ? rmdir($file) : unlink($file);
				$counter += 1;
			}
		}

		return $counter;
	}
}

Cache::$CACHE_LOCATION = &\CONFIG::$CACHE_ROOT;
