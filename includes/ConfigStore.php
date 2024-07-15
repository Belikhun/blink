<?php

/**
 * ConfigStore.php
 * 
 * Config Store classes and API.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Config;

use Blink\Exception\CodingError;
use Blink\FileIO;
use CONFIG;

class StoreGroup {
	public string $title;

	/** @var StoreItem[] */
	public $items;

	public function __construct(string $title) {
		$this -> title = $title;
		$this -> items = array();
		Store::$groups[] = $this;
	}

	public function define(
		string $name,
		string $title,
		string $description = null
	) {
		if (!property_exists("CONFIG", $name))
			throw new CodingError("\Config\StoreGroup::define($name): config does not exist!");

		$this -> items[] = new StoreItem(
			$name,
			$title,
			gettype(CONFIG::$$name),
			$description,
		);
	}
}

class StoreItem {
	public string $name;
	public string $title;
	public string $type;
	public ?string $description = null;

	public function __construct(
		$name,
		$title,
		$type,
		$description
	) {
		$this -> name = $name;
		$this -> title = $title;
		$this -> type = $type;
		$this -> description = $description;
	}
}

class Store {
	/** @var StoreGroup[] */
	public static $groups = array();

	/** @var FileIO */
	public static $CONFIG_FILE;

	/** @var string */
	public static $CONFIG_PATH;

	public static function init() {
		self::$CONFIG_FILE = new FileIO(self::$CONFIG_PATH, new \stdClass, TYPE_JSON); 
		self::load();
	}

	/**
	 * Get all configuration names
	 * @return string[]
	 */
	protected static function names() {
		$names = array();

		foreach (self::$groups as $group) {
			foreach ($group -> items as $item) {
				$names[] = $item -> name;
			}
		}

		return $names;
	}

	/**
	 * Get config object.
	 * @return object
	 */
	public static function config() {
		$object = new \stdClass;
		$names = self::names();
		
		foreach ($names as $name)
			$object -> $name = CONFIG::$$name;

		return $object;
	}

	public static function set(string $name, $value) {
		if (!property_exists("CONFIG", $name))
			throw new CodingError("\Config\Store::set($name): config does not exist!");

		CONFIG::$$name = $value;
	}

	public static function load() {
		// No config groups are defined, so we better do nothing
		// from here.
		if (empty(self::$groups))
			return;

		$config = self::$CONFIG_FILE -> read(TYPE_JSON_ASSOC);

		if (empty($config)) {
			self::save();
			return;
		}

		foreach ($config as $name => $value)
			CONFIG::$$name = $value;
	}

	public static function save() {
		$config = self::config();
		self::$CONFIG_FILE -> write($config, TYPE_JSON);
	}
}
