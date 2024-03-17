<?php

namespace Blink;

use Blink\Exception\InvalidProperty;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use ReflectionAttribute;
use ReturnTypeWillChange;
use Blink\Attribute\Lazyload;
use Blink\Attribute\SensitiveField;
use Blink\Exception\CodingError;
use Blink\Exception\ModelInstanceNotFound;
use Blink\Exception\RuntimeError;
use Blink\Query;
use stdClass;

/**
 * Model.php
 * 
 * A dababase model.
 * 
 * @template	M
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Model implements JsonSerializable {
	const MUST_EXIST = 1;

	const IGNORE_MISSING = 0;

	const NO_FILL = "justareallylongvaluehopefullythisisuniqueenough";

	/**
	 * The table name in database that used to store records for
	 * this class. Table name prefix will be automatically included.
	 *
	 * @var string
	 */
	public static String $table;

	/**
	 * Permission name related to this model.
	 *
	 * @var string
	 */
	public static String $permissionName;

	/**
	 * Define fillable fields in the parent class.
	 * This also define key mapping between database fields and
	 * class properties. Syntax: `[ objKey => dbKey ]`
	 *
	 * @var array
	 */
	public static $fillables = Array();

	/**
	 * Primiary key name for this model.
	 *
	 * @var string
	 */
	public static String $primaryKey = "id";

	/**
	 * Created time key name for this model.
	 *
	 * @var string
	 */
	protected static String $createdKey = "created";

	/**
	 * Updated time key name for this model.
	 *
	 * @var string
	 */
	protected static String $updatedKey = "updated";

	/**
	 * Instances of fetched models, mapped by ID.
	 *
	 * @var array<string, M[]>
	 */
	private static Array $instances = Array();

	/**
	 * List of lazyloading properties, mapped by ID.
	 *
	 * @var array<string, array<string, array>>
	 */
	private static Array $lazyloads = Array();

	/**
	 * List of lazyload property name mapping.
	 * `[realName => virtualName]`
	 *
	 * @var array<string, array<string, string>>
	 */
	private static Array $lazyloadMap = Array();

	/**
	 * List of lazyload property to be loaded when getting it's value.
	 * `[realName => virtualName]`
	 *
	 * @var array<string, array<string, bool>>
	 */
	private static Array $lazyloadWillProcess = Array();

	/**
	 * Set this to true if this model contain fields from
	 * other table and require to join with this. This
	 * is to prevent field conflicts.
	 *
	 * @var bool
	 */
	protected static bool $haveExternalField = false;

	/**
	 * Signal variable indicate if we can handle model-based
	 * events (ex. user_created event) to prevent race condition.
	 *
	 * @var bool
	 */
	public static bool $canHandleModelEvents = true;

	/**
	 * Current query conditions for this model.
	 *
	 * @var string
	 */
	protected Array $conditions = Array();

	protected static Array $dbMaps = Array();

	/**
	 * ID of this model instance.
	 *
	 * @var ?string
	 */
	private ?String $uniqueModelInstanceID = null;

	/**
	 * ID of this instance, in database.
	 * This ID will still available to use after record is deleted in database.
	 *
	 * @var ?string
	 */
	protected ?int $oldID = null;

	/**
	 * Return full column name of this model, based on model's key name.
	 *
	 * @param	string	$name	Model's key name. Must be defined in fillable.
	 */
	public static function col(String $name) {
		return static::$table . "." . static::mapDB($name);
	}

	/**
	 * Initialiate query in raw mode.
	 *
	 * @return Query<M>
	 */
	public static function raw(String $sql, Array $params = Array()) {
		$query = new Query(static::class, static::$table);
		return $query -> sql($sql, $params);
	}

	/**
	 * Initialiate query in raw mode.
	 *
	 * @return Query<M>
	 */
	public static function sql(String $sql, Array $params = Array()) {
		return static::raw($sql, $params);
	}

	/**
	 * Add select condtition to this model query.
	 *
	 * @return Query<M>
	 */
	public static function where(...$args) {
		return static::query() -> where(...$args);
	}

	/**
	 * Create a new query for this model.
	 *
	 * @return Query<M>
	 */
	public static function query() {
		if (empty(static::$table))
			throw new CodingError(self::class . "::query(): cannot call this function without target table defined! either define it in your class or use <code>Blink\\DB::table()</code>");

		return new Query(static::class, static::$table);
	}

	/**
	 * Fetch all instances of this model from database.
	 *
	 * @return M[]
	 */
	public static function all() {
		global $DB;

		if (empty(static::$table))
			throw new CodingError(self::class . "::query(): cannot call this function without target table defined! either define it in your class or use <code>Blink\\DB::table()</code>");

		$records = $DB -> records(static::$table);
		return static::processRecords($records);
	}

	/**
	 * Create a new instance of this object.
	 *
	 * @param	array	$fill
	 * @return	M
	 */
	public static function create(Array $fill = Array()) {
		if (static::class === self::class || static::class === DB::class)
			throw new CodingError(self::class . "::create(): this function can only be used on an inherited Model.");

		$instance = new static();
		$instance -> fill($fill);

		// Remember to disable signal after task completed.
		static::$canHandleModelEvents = true;

		return $instance;
	}

	/**
	 * Return the primary value mapped by primary key.
	 *
	 * @return ?int
	 */
	public function getPrimaryValue() {
		if (!isset($this -> {static::$primaryKey}))
			return null;

		return $this -> saveField(static::$primaryKey);
	}

	/**
	 * Return the ID of this model instance, for mapping with lazyload.
	 *
	 * @return string
	 */
	public function getInstanceID() {
		if ($this -> uniqueModelInstanceID === null)
			$this -> uniqueModelInstanceID = randString(8);

		return $this -> uniqueModelInstanceID;
	}

	/**
	 * Fill values from array to this object.
	 *
	 * @return M
	 */
	public function fill(Array $values) {
		foreach ($values as $key => $value) {
			if ($value === static::NO_FILL)
				continue;

			$this -> set($key, $value);
		}

		return $this;
	}

	/**
	 * Set a value in this object.
	 * This will try to call setter for the property first (ex. `setSomething()`) then set the property directly.
	 *
	 * @param	string		$key
	 * @param	mixed		$value
	 * @return	M
	 */
	public function set(String $key, $value) {
		$method = "set" . ucfirst($key);
		static::normalizeMaps();

		if (method_exists($this, $method))
			$this -> {$method}($value);
		else if (property_exists($this, $key))
			$this -> {$key} = $value;
		else if (isset(static::$lazyloadMap[static::class][$key])) {
			$virtKey = static::$lazyloadMap[static::class][$key];
			$this -> {$virtKey} = $value;

			$id = $this -> getInstanceID();
			self::$lazyloadWillProcess[static::class][$id][$key] = false;
		}

		if ($key === static::$primaryKey)
			$this -> oldID = $value;

		return $this;
	}

	/**
	 * Save changes made to this object to database.
	 * If the record was newly created, it will be inserted into
	 * database instead.
	 *
	 * @return M
	 */
	public function save() {
		global $DB;

		$this -> beforeSave();

		$insert = empty($this -> {static::$primaryKey});
		static::normalizeMaps();

		if (property_exists($this, static::$createdKey) && $insert)
			$this -> {static::$createdKey} = time();

		if (property_exists($this, static::$updatedKey))
			$this -> {static::$updatedKey} = time();

		$record = $this -> toRecord(!$insert);

		if ($insert) {
			// Record was newly created.
			$id = $DB -> insert(static::$table, $record);
			$this -> {static::$primaryKey} = $id;
			self::saveInstance($this -> {static::$primaryKey}, $this);

			$this -> onInstanceCreated();
			$this -> onCreated();
		} else {
			$record -> {static::mapDB(static::$primaryKey)} = $this -> saveField(static::$primaryKey);
			$DB -> update(static::$table, $record, static::$primaryKey);
		}

		$this -> onSaved();
		return $this;
	}

	/**
	 * Delete this model instance in the database.
	 *
	 * @param	?ProgressReporter	$reporter
	 * @return 	M
	 */
	public function delete(?ProgressReporter $reporter = null) {
		global $DB;

		// Don't let core handle deleted event related to models.
		static::$canHandleModelEvents = false;

		$name = static::class . " [" . $this -> {static::$primaryKey} . "]";
		$child = $reporter ?-> newChild();
		$reporter ?-> report(
			0,
			ProgressReporter::INFO,
			"Preparing to delete {$name}"
		);

		// Let the model prepare for deletion first, this might be deleting related
		// row with this instance.
		$this -> beforeDelete($child);

		$reporter ?-> report(
			0.5,
			ProgressReporter::INFO,
			"Deleting {$name}"
		);

		$DB -> delete(
			static::$table,
			Array( static::$primaryKey => $this -> saveField(static::$primaryKey) )
		);

		$reporter ?-> report(
			0.9,
			ProgressReporter::INFO,
			"Cleaning up {$name}"
		);

		self::removeInstance($this -> {static::$primaryKey});
		unset($this -> {static::$primaryKey});

		$this -> onInstanceDeleted();
		$this -> onDeleted($child);

		$child ?-> setCompleted();
		$reporter ?-> report(
			1,
			ProgressReporter::OKAY,
			"Successfully deleted {$name}"
		);

		// Now we can safely enable this again.
		static::$canHandleModelEvents = true;

		return $this;
	}

    /**
	 * Event function that will be called when a new instance
	 * of this is fully loaded.
	 *
	 * @return void
	 */
	protected function onLoaded() {}

	/**
	 * Event function that will be called when a new instance
	 * of this has been created.
	 *
	 * @return void
	 */
	protected function onCreated() {}

	/**
	 * Event function that will be called when current instance
	 * of this will be deleted from database.
	 *
	 * @param	?ProgressReporter	$reporter
	 * @return void
	 */
	protected function beforeDelete(?ProgressReporter $reporter = null) {}

	/**
	 * Event function that will be called when current instance
	 * of this has been deleted from database.
	 *
	 * @return void
	 */
	protected function onDeleted(?ProgressReporter $reporter = null) {}

	/**
	 * Function for validating if we can safely delete this model, and how many more records will be deleted with this.
	 * If the first item return false, it's mean this delete operation require strict confirmation from user.
	 *
	 * @return	array	First item indicates if we can delete this instance, second item indicates amount of records will be removed from the database before this.
	 */
	public function canDelete() {
		return Array(true, 1);
	}

	/**
	 * Event function that will be called when current instance
	 * `save()` function has been called, before saving actually happend.
	 *
	 * @return void
	 */
	protected function beforeSave() {}

	/**
	 * Event function that will be called when current instance
	 * `save()` function has been called, and saving is completed.
	 *
	 * @return void
	 */
	protected function onSaved() {}

	/**
	 * Event function that will be called when current instance
	 * has been created/inserted into database. INTERNAL USE ONLY!
	 *
	 * @return void
	 */
	protected function onInstanceCreated() {}

	/**
	 * Event function that will be called when current instance
	 * has been deleted from database. INTERNAL USE ONLY!
	 *
	 * @return void
	 */
	protected function onInstanceDeleted() {}

	protected static function saveInstance(int $id, Model $instance) {
		if (!isset(self::$instances[static::class]))
			self::$instances[static::class] = Array();

		self::$instances[static::class][$id] = $instance;
	}

	/**
	 * Try to get saved instance of current model by ID.
	 *
	 * @return ?M
	 */
	protected static function getInstance(int $id) {
		if (!isset(self::$instances[static::class]))
			return null;

		if (empty(self::$instances[static::class][$id]))
			return null;

		return self::$instances[static::class][$id];
	}

	protected static function removeInstance(int $id) {
		if (!isset(self::$instances[static::class]))
			return false;

		unset(self::$instances[static::class][$id]);
		return true;
	}

	/**
	 * Normalize mapping arrays for this object.
	 */
	public static function normalizeMaps() {
		if (!isset(static::$lazyloadMap[static::class])) {
			static::$lazyloadMap[static::class] = Array();
			$reflection = new ReflectionClass(static::class);

			/** @var ReflectionProperty[] */
			$props = $reflection -> getProperties(ReflectionProperty::IS_PUBLIC
				| ReflectionProperty::IS_PROTECTED);

			foreach ($props as $prop) {
				/** @var ReflectionAttribute[] */
				$attrs = $prop -> getAttributes(Lazyload::class);

				if (!empty($attrs)) {
					$attr = reset($attrs);

					// Assuming mapping name is the same.
					$realName = (!empty($attr -> getArguments()[0]))
						? $attr -> getArguments()[0]
						: $attr -> getName();

					static::$lazyloadMap[static::class][$realName] = $prop -> getName();
				}
			}
		}

		if (!isset(static::$dbMaps[static::class])) {
			$normalized = Array();

			foreach (static::$fillables as $objKey => $dbKey) {
				// If objKey is a number, this is a normal item in array without key value.
				// This mean mapping key between database and object is the same.
				if (is_int($objKey))
					$objKey = $dbKey;

				$normalized[$objKey] = $dbKey;
			}

			static::$fillables = $normalized;
			static::$dbMaps[static::class] = array_flip($normalized);
		}
	}

	/**
	 * Return mapping from object key to db key.
	 *
	 * @return string
	 */
	public static function mapDB(String $objKey) {
		if (empty(static::$fillables))
			return $objKey;

		static::normalizeMaps();

		return isset(static::$fillables[$objKey])
			? static::$fillables[$objKey]
			: $objKey;
	}

	/**
	 * Return mapping from db key to object key.
	 *
	 * @return string
	 */
	public static function mapObj(String $dbKey) {
		if (empty(static::$fillables))
			return $dbKey;

		static::normalizeMaps();

		if (!isset(static::$dbMaps[static::class][$dbKey]) || is_int(static::$dbMaps[static::class][$dbKey]))
			return $dbKey;

		return static::$dbMaps[static::class][$dbKey];
	}

	/**
	 * Get instance of this model, by ID.
	 * Will return cached instance if available.
	 *
	 * @param	int		$id
	 * @param	int		$strict		Strictness. Default to IGNORE_MISSING, which will return null when instance not found.
	 * @return	?M
	 */
	public static function getByID(
		?int $id = null,
		int $strict = Model::IGNORE_MISSING
	) {
		if (static::class === self::class || static::class === DB::class)
			throw new CodingError(self::class . "::getByID(): this function can only be used on an inherited Model.");

		if (empty($id)) {
			if ($strict === Model::MUST_EXIST)
				throw new ModelInstanceNotFound(static::class, $id);

			return null;
		}

		$instance = self::getInstance($id);

		if (!empty($instance))
			return $instance;

		$instance = static::where(static::$primaryKey, $id)
			-> first();

		if (empty($instance)) {
			if ($strict === Model::MUST_EXIST)
				throw new ModelInstanceNotFound(static::class, $id);

			return null;
		}

		return $instance;
	}

	/**
	 * Convert model into full, insertable record object.
	 * 
	 * @param	bool		$includePrimary		Include primary key value in record object.
	 * @return	stdClass
	 */
	public function toRecord(bool $includePrimary = true) {
		static::normalizeMaps();
		$record = new stdClass;

		foreach (static::$fillables as $objKey => $dbKey) {
			if (!$includePrimary && $objKey === static::$primaryKey)
				continue;

			$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
				&& !empty(static::$lazyloadMap[static::class][$objKey]);

			if ($hasLazyloadMapping) {
				$virtKey = static::$lazyloadMap[static::class][$objKey];

				// Property is not initialized, fallback to saved lazyload mapping value.
				if (!property_exists($this, $virtKey)) {
					if (!isset(self::$lazyloads[static::class]) || empty(self::$lazyloads[static::class][$this -> getInstanceID()]))
						throw new RuntimeError(-1, "Trying to access uninitialized property \"{$objKey}\" of [" . static::class . "]");

					$value = self::$lazyloads[static::class][$this -> getInstanceID()][$objKey];
					$record -> {$dbKey} = $value;
					continue;
				}
			}

			$record -> {$dbKey} = $this -> saveField($objKey);
		}

		return $record;
	}

	/**
	 * Process field name before applying to object.
	 *
	 * @param	string	$name	Field name. Use object field naming.
	 * @param	mixed	$value	Value returned from record.
	 * @return	mixed	New value will be applied to object.
	 */
	protected static function processField(String $name, $value) {
		return $value;
	}

	/**
	 * Return field value that will be saved into database.
	 *
	 * @param string $name
	 * @return mixed Value of this field that will be put into database.
	 */
	protected function saveField(String $name) {
		return $this -> {$name};
	}

	/**
	 * Process the returned record from database.
	 *
	 * @param	object	The record object `$DB -> get_record()` API returned.
	 * @return	M
	 */
	public static function processRecord(stdClass $record) {
		$instance = new static();
		static::normalizeMaps();

		foreach (static::$fillables as $objKey => $dbKey) {
			$value = $record -> {$dbKey};

			$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
				&& !empty(static::$lazyloadMap[static::class][$objKey]);

			if ($hasLazyloadMapping) {
				// Update mapped value to attribute.
				if (!isset(self::$lazyloads[static::class]))
					self::$lazyloads[static::class] = Array();

				$id = $instance -> getInstanceID();

				if (!isset(self::$lazyloads[static::class][$id]))
					self::$lazyloads[static::class][$id] = Array();

				self::$lazyloads[static::class][$id][$objKey] = (is_numeric($value))
					? (float) $value
					: $value;

				self::$lazyloadWillProcess[static::class][$id][$objKey] = true;
				continue;
			}

			$value = static::processField($objKey, $value);
			$instance -> set($objKey, $value);
		}

		// Cache our instance for future use.
		self::saveInstance($instance -> {static::$primaryKey}, $instance);

        // Invoke the onLoaded event.
        $r = new ReflectionMethod($instance, "onLoaded");
        $r -> setAccessible(true);
        $r -> invoke($instance);

		return $instance;
	}

	/**
	 * Process a list of returned records from database.
	 *
	 * @param	object[]	The record objects array `$DB -> get_records()` API returned.
	 * @return	M[]
	 */
	public static function processRecords(Array $records) {
		foreach ($records as &$record)
			$record = static::processRecord($record);

		return $records;
	}

	/**
	 * Return lazyload mapped name of a property name.
	 *
	 * @param	string	$name
	 * @return	?string
	 */
	protected static function getLazyloadMapName(String $name) {
		$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
			&& !empty(static::$lazyloadMap[static::class][$name]);

		if (!$hasLazyloadMapping)
			return null;

		return static::$lazyloadMap[static::class][$name];
	}

	/**
	 * Get field value.
	 * This will also process lazyloading properties.
	 *
	 * @param	string		$name			Field name
	 * @param	bool		$sensitive		Return value even if the field is sensitive.
	 * @param	bool		$safe			Return null when value is not set, else throw error.
	 * @return	mixed
	 */
	public function get(String $name, bool $sensitive = false, bool $safe = false) {
		// Remove ID prefix.
		if (str_ends_with($name, "ID") && !property_exists($this, $name)) {
			$realName = preg_replace('/ID$/', "", $name);
			$lazyloadMap = static::getLazyloadMapName($realName);

			if ($lazyloadMap && empty($this -> {$lazyloadMap})) {
				$id = $this -> getInstanceID();
				$hasLazyloading = !empty(self::$lazyloads[static::class])
					&& !empty(self::$lazyloads[static::class][$id])
					&& !empty(self::$lazyloads[static::class][$id][$realName]);

				if ($hasLazyloading)
					return self::$lazyloads[static::class][$id][$realName];
			} else if (!empty($this -> {$lazyloadMap})) {
				if ($this -> {$lazyloadMap} instanceof Model)
					return $this -> {$lazyloadMap} -> getPrimaryValue();
				else if ($this -> {$lazyloadMap} instanceof stdClass && !empty($this -> {$lazyloadMap} -> id))
					return (int) $this -> {$lazyloadMap} -> id;
			}
		}

		$realName = $name;
		$lazyloadMap = static::getLazyloadMapName($name);

		if ($lazyloadMap)
			$name = $lazyloadMap;

		if (!property_exists($this, $name))
			throw new InvalidProperty($name, static::class);

		$id = $this -> getInstanceID();

		$processLazyloadValue = !empty(self::$lazyloadWillProcess[static::class])
			&& !empty(self::$lazyloadWillProcess[static::class][$id])
			&& isset(self::$lazyloadWillProcess[static::class][$id][$realName])
			&& self::$lazyloadWillProcess[static::class][$id][$realName] === true;

		if ($processLazyloadValue) {
			$hasLazyloading = !empty(self::$lazyloads[static::class])
				&& !empty(self::$lazyloads[static::class][$id])
				&& !empty(self::$lazyloads[static::class][$id][$realName]);

			if ($hasLazyloading) {
				// Update mapped value to attribute.
				$value = static::processField($realName, self::$lazyloads[static::class][$id][$realName]);
				$this -> {$name} = $value;
			}

			self::$lazyloadWillProcess[static::class][$id][$realName] = false;
		}

		if (!$sensitive) {
			$reflection = new ReflectionObject($this);
			$property = $reflection -> getProperty($name);
			$attrs = $property -> getAttributes(SensitiveField::class);

			if (!empty($attrs))
				return null;
		}

		return ($safe && !isset($this -> {$name}))
			? null
			: $this -> {$name};
	}

	public function __get(String $name) {
		return $this -> get($name, true);
	}

	public function __set(String $name, $value) {
		$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
			&& !empty(static::$lazyloadMap[static::class][$name]);

		if ($hasLazyloadMapping) {
			$virtName = static::$lazyloadMap[static::class][$name];
			$this -> {$virtName} = $value;
			return;
		}

		$this -> {$name} = $value;
	}

	public function __isset(String $name) {
		$realName = $name;
		$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
			&& !empty(static::$lazyloadMap[static::class][$name]);

		if ($hasLazyloadMapping) {
			$id = $this -> getInstanceID();
			return isset(self::$lazyloads[static::class][$id][$realName]);
		}

		return isset($this -> {$name});
	}

	/**
	 * Return array object of this model instance.
	 * Used for sending information to client.
	 *
	 * @param	bool	$sensitive		Include sensitive fields.
	 * @param	bool	$safe			Set value to null when value is not set, else throw error.
	 * @param	int		$depth			Maximum depth we will return model instances. -1 to disable depth restriction.
	 * @return	array
	 */
	public function out(bool $sensitive = false, bool $safe = false, int $depth = -1) {
		$out = Array();

		static::normalizeMaps();
		$ref = new ReflectionClass($this);

		foreach (static::$fillables as $objKey => $dbKey) {
			if ($depth > 0) {
				$lazyloadMap = static::getLazyloadMapName($objKey);

				if ($lazyloadMap) {
					$prop = $ref -> getProperty($lazyloadMap);

					if (is_a((String) $prop -> getType(), self::class, true)) {
						if ($depth <= 1) {
							$out[$objKey] = null;
							continue;
						}

						$out[$objKey] = $this -> get($objKey, $sensitive, $safe)
							?-> out($sensitive, $safe, $depth - 1);

						continue;
					}
				}
			}

			$out[$objKey] = $this -> get($objKey, $sensitive, $safe);
		}

		return $out;
	}

	public function __serialize() {
		return $this -> out(false, true);
	}

	/**
	 * Return json serializable data.
	 *
	 * @param	int		$depth		Maximum depth we will return model instances. -1 to disable depth restriction.
	 * @return	array
	 */
    #[ReturnTypeWillChange]
	public function jsonSerialize(int $depth = -1) {
		$out = Array();

		static::normalizeMaps();
		$ref = new ReflectionClass($this);

		foreach (static::$fillables as $objKey => $dbKey) {
			if ($depth >= 0) {
				$lazyloadMap = static::getLazyloadMapName($objKey);

				if ($lazyloadMap) {
					$prop = $ref -> getProperty($lazyloadMap);

					if (is_a((String) $prop -> getType(), self::class, true)) {
						if ($depth <= 1) {
							$out[$objKey] = null;
							continue;
						}

						$out[$objKey] = $this -> get($objKey, false, true)
							?-> jsonSerialize($depth - 1);

						continue;
					}
				}
			}

			$out[$objKey] = $this -> get($objKey, false, true);
		}

		return $out;
	}

	/**
	 * Return model display name, or localized name if available.
	 *
	 * @return string
	 */
	public static function modelName() {
		// Default to return class name.
		$name = explode("\\", static::class);
		return end($name);
	}
}
