<?php

namespace Blink;

use stdClass;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use ReflectionAttribute;
use ReturnTypeWillChange;
use Blink\Attribute\Lazyload;
use Blink\Attribute\SensitiveField;
use Blink\DB\Database;
use Blink\DB\Exception\DatabaseNotRegistered;
use Blink\Exception\CodingError;
use Blink\Exception\InvalidProperty;
use Blink\Exception\ModelInstanceNotFound;
use Blink\Exception\RuntimeError;
use Blink\Query\Expression\Column;
use Blink\Query;
use Blink\Query\Expression\Table;

/**
 * Represent a database model.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * @template	M
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
	public static string $table;

	/**
	 * Name of the database instance the table lives on. `null` is the
	 * default. Any other name has to be registered: a model never quietly
	 * reads the default's table in place of the one it names.
	 *
	 * @var ?string
	 */
	public static ?string $database = null;

	/**
	 * Permission name related to this model.
	 *
	 * @var string
	 */
	public static string $permissionName;

	/**
	 * Define fillable fields in the parent class.
	 * This also define key mapping between database fields and
	 * class properties. Syntax: `[ objKey => dbKey ]`
	 *
	 * @var array
	 */
	public static $fillables = array();

	/**
	 * Primiary key name for this model.
	 *
	 * @var string
	 */
	public static string $primaryKey = "id";

	/**
	 * Created time key name for this model.
	 *
	 * @var string
	 */
	protected static string $createdKey = "created";

	/**
	 * Updated time key name for this model.
	 *
	 * @var string
	 */
	protected static string $updatedKey = "updated";

	/**
	 * Instances of fetched models, mapped by ID.
	 *
	 * @var array<string, M[]>
	 */
	private static array $instances = array();

	/**
	 * How deep the running deletions are nested. The outermost `delete()` owns
	 * rolling the journal back when the shared transaction fails.
	 *
	 * @var int
	 */
	private static int $deletionDepth = 0;

	/**
	 * Teardown journal for the running deletion. Each entry is an instance,
	 * this one or a related one removed by `beforeDelete()`, recorded with
	 * enough to put it back if the transaction rolls back.
	 *
	 * @var array<array{0: Model, 1: string, 2: int, 3: string}>
	 */
	private static array $deletionJournal = array();

	/**
	 * List of lazyloading properties, mapped by ID.
	 *
	 * @var array<string, array<string, array>>
	 */
	private static array $lazyloads = array();

	/**
	 * List of lazyload property name mapping.
	 * `[realName => virtualName]`
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $lazyloadMap = array();

	/**
	 * List of lazyload property to be loaded when getting it's value.
	 * `[realName => virtualName]`
	 *
	 * @var array<string, array<string, bool>>
	 */
	private static array $lazyloadWillProcess = array();

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
	 * Specical signal to indicate we are initializing
	 * this model instance and populating the model data from database.
	 *
	 * @var bool
	 */
	public bool $isPopulatingData = false;

	protected static array $dbMaps = array();

	/**
	 * ID of this model instance.
	 *
	 * @var ?string
	 */
	private ?string $uniqueModelInstanceID = null;

	/**
	 * ID of this instance, in database.
	 * This ID will still be available to use after record is deleted in database.
	 *
	 * @var ?string
	 */
	protected ?int $oldID = null;

	/**
	 * Store current database snapshot of this model. Used for checking if a property's
	 * value has been changed and require an update to database.
	 *
	 * @var array
	 */
	protected array $snapshot = array();

	/**
	 * Flag indicate that this model instance is in the process of being deleted.
	 *
	 * @var bool
	 */
	protected bool $instanceDeleting = false;

	protected ReflectionObject $reflectionObject;

	/**
	 * Return table object for this model.
	 *
	 * @return	Table
	 */
	public static function t(): Table {
		return Table::instance(static::$table, static::database());
	}

	/**
	 * The instance this model reads from and writes to.
	 *
	 * @throws	\Blink\DB\Exception\DatabaseNotRegistered
	 */
	public static function database(): Database {
		if (static::$database !== null && static::$database !== Database::DEFAULT && !Database::has(static::$database))
			throw new DatabaseNotRegistered(static::$database, "Model <code>" . static::class . "</code> is stored on it.");

		return Database::resolve(static::$database);
	}

	/**
	 * Return full column name of this model, based on model's key name.
	 *
	 * @param	string		$name		Model's key name. Must be defined in fillable.
	 * @return	Column
	 */
	public static function col(string $name): Column {
		if ($name === "#")
			return static::col(static::$primaryKey);

		if ($name === "*")
			return Column::instance(static::t(), "*");

		return Column::instance(static::t(), static::mapDB($name));
	}

	/**
	 * Initialiate query in raw mode.
	 *
	 * @return Query<M>
	 */
	public static function raw(string $sql, array $params = array()) {
		$query = new Query(static::class, "");
		return $query -> sql($sql, $params);
	}

	/**
	 * Initialiate query in raw mode.
	 *
	 * @return Query<M>
	 */
	public static function sql(string $sql, array $params = array()) {
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
			throw new CodingError(self::class . "::query(): cannot call this function without target table defined! either define it in your class or use Blink\\DB::table()");

		return new Query(static::class, static::$table);
	}

	/**
	 * Fetch all instances of this model from database.
	 *
	 * @return M[]
	 */
	public static function all() {
		if (empty(static::$table))
			throw new CodingError(self::class . "::query(): cannot call this function without target table defined! either define it in your class or use Blink\\DB::table()");

		$records = array_values(static::database() -> records(static::$table));
		return static::processRecords($records);
	}

	/**
	 * Create a new instance of this object.
	 *
	 * @param	array|object	$fill
	 * @return	M
	 */
	public static function create(array|object $fill = array()) {
		if (static::class === self::class || static::class === 'Blink\DB')
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
	 * Check if a property key exists in this model instance.
	 *
	 * @param	string	$key
	 * @return	bool
	 */
	public function isPropertyExists(string $key) {
		if (static::getLazyloadMapName($key))
			return true;

		return property_exists($this, $key);
	}

	/**
	 * Fill values from array to this object.
	 *
	 * @param	array|object	$values		Values to fill into this model instance.
	 * @return	M
	 */
	public function fill(array|object $values) {
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
	public function set(string $key, $value) {
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

	public function getReflection(): ReflectionObject {
		if (empty($this -> reflectionObject))
			$this -> reflectionObject = new ReflectionObject($this);

		return $this -> reflectionObject;
	}

	public function isInserted() {
		return !empty($this -> {static::$primaryKey});
	}

	/**
	 * Convert model into full, insertable record object.
	 *
	 * @param	bool		$primary		Include primary key value in record object.
	 * @param	bool		$unchanged		Include all property with unchanged values.
	 * @return	stdClass
	 */
	public function toRecord(bool $primary = true, bool $unchanged = true) {
		static::normalizeMaps();
		$record = new stdClass;

		foreach (static::$fillables as $objKey => $dbKey) {
			if (!$primary && $objKey === static::$primaryKey)
				continue;

			// Check for property value changed, and skip including it into returning
			// object if it's not changed. We also skip this check for primary key.
			if (!$unchanged && $objKey !== static::$primaryKey && !$this -> isDirty($objKey))
				continue;

			$record -> {$dbKey} = $this -> getSaveValue($objKey);
		}

		return $record;
	}

	/**
	 * Get and return value that will be saved into the database for the given property key.
	 *
	 * @param	string	$key
	 * @return	mixed
	 */
	public function getSaveValue($key) {
		$virtKey = static::getLazyloadMapName($key);

		if ($virtKey) {
			$id = $this -> getInstanceID();
			$processLazyloadValue = !empty(self::$lazyloadWillProcess[static::class])
				&& !empty(self::$lazyloadWillProcess[static::class][$id])
				&& isset(self::$lazyloadWillProcess[static::class][$id][$key])
				&& self::$lazyloadWillProcess[static::class][$id][$key] === true;

			// Property is pending for lazyload hit, we can safely return stored value
			// from lazyload mapping info.
			if ($processLazyloadValue)
				return self::$lazyloads[static::class][$this -> getInstanceID()][$key];

			$propertyInitialized = $this
				-> getReflection()
				-> getProperty($virtKey)
				-> isInitialized($this);

			// Property is not initialized, fallback to saved lazyload mapping value.
			if (!$propertyInitialized) {
				if (!isset(self::$lazyloads[static::class]) || empty(self::$lazyloads[static::class][$this -> getInstanceID()]))
					throw new RuntimeError(-1, "Trying to access uninitialized property \"{$key}\" of [" . static::class . "]");

				return self::$lazyloads[static::class][$this -> getInstanceID()][$key];
			}
		}

		return $this -> saveField($key);
	}

	/**
	 * Save changes made to this object to database.
	 * If the record was newly created, it will be inserted into
	 * database instead.
	 *
	 * @return M
	 */
	public function save() {
		$this -> beforeSave();

		$record = array();
		$insert = !$this -> isInserted();
		static::normalizeMaps();

		if (property_exists($this, static::$createdKey) && $insert && empty($this -> {static::$createdKey}))
			$this -> {static::$createdKey} = time();

		// The stamp moves only when something else did. Setting it on every
		// save would make a save that touched nothing look like a change: it
		// would write a row, and announce an update carrying nothing but the
		// stamp that the save itself had just moved.
		if (property_exists($this, static::$updatedKey)) {
			$tracked = array_values(array_diff(
				array_keys(static::$fillables),
				array( static::$primaryKey, static::$updatedKey )
			));

			if ($insert || (!empty($tracked) && $this -> isDirty($tracked)))
				$this -> {static::$updatedKey} = time();
		}

		$record = $this -> toRecord(!$insert, $insert);

		if ($insert) {
			// Record was newly created.
			$id = static::database() -> insert(static::$table, $record);
			$this -> {static::$primaryKey} = $id;
			self::saveInstance($this -> {static::$primaryKey}, $this);

			$this -> onInstanceCreated();
			$this -> onCreated();
		} else {
			$record -> {static::mapDB(static::$primaryKey)} = $this -> saveField(static::$primaryKey);

			// Only update if we actually have data to update.
			if (count(array_keys(get_object_vars($record))) > 1)
				static::database() -> update(static::$table, (Object) $record);
		}

		$this -> onSaved();

		// Collected before the snapshot is refreshed: afterwards the baseline
		// equals the current values and the delta would come out empty.
		$changes = $insert
			? $this -> collectInitialValues()
			: $this -> collectChanges();

		$this -> takeSnapshot();

		// An update that changed nothing is not one. `ModelSaved` below still
		// fires, since the save did happen.
		if ($insert || !empty($changes)) {
			$this -> emitModelEvent($insert ? Event\ModelCreated::class : Event\ModelUpdated::class, extra: array(
				"changes" => $changes
			));
		}

		$this -> emitModelEvent(Event\ModelSaved::class, extra: array(
			"changes" => $changes,
			"insert" => $insert
		));

		return $this;
	}

	/**
	 * Return true when this model instance is in the process of being deleted.
	 *
	 * @return bool
	 */
	public function isDeleting() {
		return $this -> instanceDeleting;
	}

	/**
	 * Delete this model instance in the database.
	 *
	 * The whole teardown runs in one transaction: the related rows
	 * `beforeDelete()` clears, the record itself, and the hooks afterwards
	 * either all commit or all roll back. A rollback also puts the in-memory
	 * instances back, so a caller holding one still has something usable
	 * rather than a half-erased object.
	 *
	 * @param	?ProgressReporter	$reporter
	 * @return 	M
	 */
	public function delete(?ProgressReporter $reporter = null) {
		if (!$this -> isInserted() || $this -> instanceDeleting)
			return $this;

		// Don't let core handle deleted event related to models.
		static::$canHandleModelEvents = false;
		$this -> instanceDeleting = true;

		$name = static::class . " [" . $this -> {static::$primaryKey} . "]";
		$primaryValue = $this -> {static::$primaryKey};
		$instanceID = $this -> getPrimaryValue();
		$child = $reporter ?-> newChild();
		$reporter ?-> report(
			0,
			ProgressReporter::INFO,
			"Preparing to delete model <code>{$name}</code>"
		);

		// A related instance torn down by `beforeDelete()` runs its own
		// `delete()` inside this transaction. Counting the nesting is what
		// lets the outermost call own the rollback for all of them.
		self::$deletionDepth++;

		try {
			static::database() -> transaction(function () use ($name, $child, $reporter, $primaryValue, $instanceID) {
				// Let the model prepare for deletion first, this might be deleting related
				// row with this instance.
				$this -> beforeDelete($child);

				$reporter ?-> report(
					0.5,
					ProgressReporter::INFO,
					"Deleting <code>{$name}</code> from database..."
				);

				static::database() -> delete(
					static::$table,
					array( static::$primaryKey => $this -> saveField(static::$primaryKey) )
				);

				$reporter ?-> report(
					0.9,
					ProgressReporter::INFO,
					"Cleaning up <code>{$name}</code> leftover data..."
				);

				self::removeInstance($this -> {static::$primaryKey});
				unset($this -> {static::$primaryKey});

				// Journalled so a later step failing can put this back.
				self::$deletionJournal[] = array( $this, static::class, $primaryValue, static::$primaryKey );

				$this -> onInstanceDeleted();
				$this -> onDeleted($child);

				// Inside the transaction, so a handler's own writes roll back
				// with the deletion if a later step fails. The id has to be
				// passed in: the instance no longer carries one.
				$this -> emitModelEvent(Event\ModelDeleted::class, $instanceID, array(
					"changes" => $this -> collectFinalValues()
				));
			});
		} catch (\Throwable $e) {
			$this -> instanceDeleting = false;

			// One transaction rolled back, so one restore pass covers every
			// instance torn down inside it.
			if (self::$deletionDepth === 1) {
				foreach (self::$deletionJournal as [ $instance, $class, $primary, $primaryKey ])
					self::restoreDeletedInstance($instance, $class, $primary, $primaryKey);
			}

			throw $e;
		} finally {
			self::$deletionDepth--;

			if (self::$deletionDepth === 0) {
				self::$deletionJournal = array();

				// Now we can safely enable this again.
				static::$canHandleModelEvents = true;
			}
		}

		$child ?-> setCompleted();
		$reporter ?-> report(
			1,
			ProgressReporter::OKAY,
			"Completed deleting <code>{$name}</code>"
		);

		$this -> instanceDeleting = false;

		return $this;
	}

	/**
	 * Put back the in-memory state of an instance whose deletion rolled back,
	 * so it can be used again as though it had never been torn down.
	 *
	 * @param	Model	$instance		The torn-down instance.
	 * @param	string	$class			The instance's model class.
	 * @param	int		$primary		The primary key value it had before deletion.
	 * @param	string	$primaryKey		The primary key property name.
	 */
	private static function restoreDeletedInstance(Model $instance, string $class, int $primary, string $primaryKey): void {
		$instance -> {$primaryKey} = $primary;
		self::$instances[$class][$primary] = $instance;
		$instance -> instanceDeleting = false;
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
	 * of this has been inserted into the database.
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
	 * Function for validating if we can safely delete this model, and how many more records will be deleted with this operation.
	 * If the first item return false, it's mean this delete operation require strict confirmation from user.
	 *
	 * @return	array	First item indicates if we can safely delete this instance, second item indicates amount of records will be removed from the database before this. Example: `[false, 3]`
	 */
	public function canDelete() {
		return array(true, 1);
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

	/**
	 * Announce that something happened to this row.
	 *
	 * Silent while a deletion is unwinding related rows, which
	 * `$canHandleModelEvents` marks: those writes belong to the teardown, not
	 * to anything a handler should react to separately.
	 *
	 * @param	class-string<Event\ModelEvent>	$eventClass
	 * @param	?int							$instanceID		The row's id, when the model no longer carries one.
	 */
	protected function emitModelEvent(string $eventClass, ?int $instanceID = null, array $extra = array()): void {
		if (!static::$canHandleModelEvents && $eventClass !== Event\ModelDeleted::class)
			return;

		$event = $eventClass::fromModel($this, $instanceID);

		if (!$event)
			return;

		if (!empty($extra))
			$event -> context = array_merge($event -> context, $extra);

		Lightrail::emitEvent($event);
	}

	/**
	 * The fillable fields this save changed, as
	 * `[objectKey => ["old" => <previous save value>, "new" => <current save value>]]`,
	 * measured against the pre-save snapshot. The primary key is left out.
	 *
	 * Values are the database-facing ones, scalars and ids, so the delta can
	 * be carried around and stored without dragging whole model graphs with
	 * it.
	 *
	 * Only meaningful for an update. See {@see Model::collectInitialValues()}
	 * for why an insert cannot use the dirty-field diff.
	 *
	 * @return	array<string, array{old: mixed, new: mixed}>
	 */
	protected function collectChanges(): array {
		$changes = array();

		foreach (array_keys(static::$fillables) as $objKey) {
			if ($objKey === static::$primaryKey || !$this -> isDirty($objKey))
				continue;

			$changes[$objKey] = array(
				"old" => $this -> snapshot[$objKey] ?? null,
				"new" => $this -> getSaveValue($objKey)
			);
		}

		return $changes;
	}

	/**
	 * Every fillable paired with the value the insert gave it, shaped like
	 * {@see Model::collectChanges()} with a null `old` throughout, since a new
	 * row is new in its entirety.
	 *
	 * An insert deliberately does not reuse the dirty-field diff. A model can
	 * already have had its snapshot baselined by the time the row is written,
	 * which a save path persisting in more than one step does, and that leaves
	 * nothing dirty. The delta for a brand new row would come out empty, and
	 * a consumer asking "did any field I care about change?" would skip
	 * creates entirely.
	 *
	 * @return	array<string, array{old: mixed, new: mixed}>
	 */
	protected function collectInitialValues(): array {
		$changes = array();

		foreach (array_keys(static::$fillables) as $objKey) {
			if ($objKey === static::$primaryKey)
				continue;

			$changes[$objKey] = array(
				"old" => null,
				"new" => $this -> getSaveValue($objKey)
			);
		}

		return $changes;
	}

	/**
	 * Every fillable paired with the value it held as the row went away,
	 * shaped like {@see Model::collectChanges()} with a null `new` throughout.
	 *
	 * This is the last chance to read them: once the deletion lands the row is
	 * gone, and a lookup by id returns nothing. Reading a field must never
	 * break the teardown, so one that cannot be read comes back null rather
	 * than throwing.
	 *
	 * @return	array<string, array{old: mixed, new: mixed}>
	 */
	protected function collectFinalValues(): array {
		$changes = array();

		foreach (array_keys(static::$fillables) as $objKey) {
			if ($objKey === static::$primaryKey)
				continue;

			try {
				$value = $this -> getSaveValue($objKey);
			} catch (\Throwable $e) {
				// A relation whose row went first, or a property never set.
				$value = null;
			}

			$changes[$objKey] = array(
				"old" => $value,
				"new" => null
			);
		}

		return $changes;
	}

	/**
	 * Drop every cached instance, of this model or of all of them.
	 *
	 * A web request ends and takes the cache with it. A long-running process
	 * does not, and would otherwise hold every row it has ever touched, so a
	 * long row loop calls this between chunks. Instances the caller still
	 * holds stay valid; they are just no longer returned by the cache, so a
	 * later fetch of the same row produces a distinct object. The long-lived
	 * instances a loop keeps using go in `$keep`, which preserves identity
	 * for those.
	 *
	 * @param	bool	$all	Clear every model rather than just this one.
	 * @param	Model[]	$keep	Instances to leave in place when clearing every model.
	 */
	public static function flushInstances(bool $all = false, array $keep = array()): void {
		if (!$all) {
			unset(self::$instances[static::class]);
			return;
		}

		$instances = array();
		$lazyloads = array();
		$willProcess = array();

		foreach ($keep as $instance) {
			if (!($instance instanceof Model))
				continue;

			$class = get_class($instance);
			$primary = $instance -> getPrimaryValue();
			$id = $instance -> getInstanceID();

			if ($primary !== null)
				$instances[$class][$primary] = $instance;

			// The relation ids a kept instance has not resolved yet live here
			// rather than on the object, so they have to survive with it.
			if (isset(self::$lazyloads[$class][$id]))
				$lazyloads[$class][$id] = self::$lazyloads[$class][$id];

			if (isset(self::$lazyloadWillProcess[$class][$id]))
				$willProcess[$class][$id] = self::$lazyloadWillProcess[$class][$id];
		}

		self::$instances = $instances;
		self::$lazyloads = $lazyloads;
		self::$lazyloadWillProcess = $willProcess;
	}

	/**
	 * Whether a property is typed as another model.
	 *
	 * Casting the type to a string was close enough while every lazyloaded
	 * property was plainly typed, but a nullable or union type stringifies to
	 * something like `?Blink\File` or `A|B`, which matches no class at all and
	 * quietly reported false.
	 */
	protected function isModelProperty(ReflectionProperty $property): bool {
		$type = $property -> getType();

		if ($type instanceof \ReflectionNamedType)
			return is_a($type -> getName(), self::class, true);

		if ($type instanceof \ReflectionUnionType) {
			foreach ($type -> getTypes() as $member) {
				if ($member instanceof \ReflectionNamedType && is_a($member -> getName(), self::class, true))
					return true;
			}
		}

		return false;
	}

	protected static function saveInstance(int $id, Model $instance) {
		if (!isset(self::$instances[static::class]))
			self::$instances[static::class] = array();

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
			static::$lazyloadMap[static::class] = array();
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
			$normalized = array();

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
	public static function mapDB(string $objKey) {
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
	public static function mapObj(string $dbKey) {
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
	 * @param	int		$strict		Strictness. Default to `IGNORE_MISSING`, which will return null when instance not found.
	 * @return	?M
	 */
	public static function getByID(
		?int $id = null,
		int $strict = Model::IGNORE_MISSING
	) {
		if (static::class === self::class || static::class === 'Blink\DB')
			throw new CodingError(self::class . "::getByID(): this function can only be used on an inherited Model.");

		if (empty($id)) {
			if ($strict === Model::MUST_EXIST)
				throw new ModelInstanceNotFound(static::class, $id);

			return null;
		}

		$instance = static::getInstance($id);

		if (!empty($instance))
			return $instance;

		$instance = static::where(static::col("#"), $id)
			-> first();

		if (empty($instance)) {
			if ($strict === Model::MUST_EXIST)
				throw new ModelInstanceNotFound(static::class, $id);

			return null;
		}

		return $instance;
	}

	/**
	 * Fetch multiple instances of this model, from the specified list of instance IDs.
	 * Will return cached instances if available.
	 *
	 * @param	int[]	$ids		ID list.
	 * @param	int		$strict		Strictness. Default to `IGNORE_MISSING`, which will return null when instance not found.
	 * @return	M[]					List of loaded model instances, mapped by ID.
	 */
	public static function getByIDs(array $ids, int $strict = Model::IGNORE_MISSING) {
		$result = array();
		$fetchIDs = array();

		foreach ($ids as $id) {
			if ($instance = static::getInstance($id)) {
				$result[$id] = $instance;
			} else {
				$fetchIDs[] = $id;
			}
		}

		// Everything has been preloaded, return the result now.
		if (empty($fetchIDs))
			return $result;

		$instances = static::where(static::col("#"), $fetchIDs)
			-> all(idIndexed: true);

		if ($strict === Model::MUST_EXIST) {
			foreach ($fetchIDs as $fetchID) {
				if (empty($instances[$fetchID]))
					throw new ModelInstanceNotFound(static::class, $fetchID);
			}
		}

		return $result + $instances;
	}

	/**
	 * Preload the specified list of instance ids.
	 *
	 * @param	int[]	$ids	ID list.
	 * @return	M[]				List of loaded model instances, mapped by ID.
	 */
	public static function preload(array $ids) {
		return static::getByIDs($ids, Model::MUST_EXIST);
	}

	/**
	 * Process field name before applying to object.
	 *
	 * @param	string	$name	Field name. Use object field naming.
	 * @param	mixed	$value	Value returned from record.
	 * @return	mixed	New value will be applied to object.
	 */
	protected static function processField(string $name, $value) {
		return $value;
	}

	/**
	 * Return field value that will be saved into database.
	 *
	 * @param	string	$name
	 * @return	mixed	Value of this field that will be put into database.
	 */
	protected function saveField(string $name) {
		return $this -> {$name};
	}

	/**
	 * Invalidate a lazyloaded field of this model instance.
	 */
	/**
	 * The primary key of a related model, without loading it.
	 *
	 * A lazy relation holds its id until something reads the property, and
	 * the loaded model afterwards. Either way the id is what a serialized
	 * payload wants when it has run out of depth.
	 *
	 * @return	?int
	 */
	protected function relationID(string $name): ?int {
		$value = $this -> get($name . "ID", true, true);

		if ($value === null)
			return null;

		return is_numeric($value) ? (int) $value : null;
	}

	public function invalidateLazyloadProperty(string $key) {
		$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
			&& !empty(static::$lazyloadMap[static::class][$key]);

		if (!$hasLazyloadMapping)
			return $this;

		$virtKey = static::$lazyloadMap[static::class][$key];
		unset($this -> {$virtKey});

		$id = $this -> getInstanceID();
		self::$lazyloadWillProcess[static::class][$id][$key] = true;

		return $this;
	}

	/**
	 * Take and store snapshot of current state of this model.
	 *
	 * @return static
	 */
	public function takeSnapshot() {
		$this -> snapshot = array();

		foreach (static::$fillables as $objKey => $dbKey)
			$this -> snapshot[$objKey] = $this -> getSaveValue($objKey);

		return $this;
	}

	/**
	 * Determine if the model or any of the given property(ies) have been modified.
	 *
	 * @param	string|string[]|null		$properties		Property name or a list of property names
	 * @return	bool
	 */
	public function isDirty(string|array|null $properties = null): bool {
		if (empty($properties))
			$properties = array_keys(static::$fillables);

		if (!is_array($properties))
			$properties = [$properties];

		foreach ($properties as $property) {
			if (!array_key_exists($property, $this -> snapshot))
				return true;

			if ($this -> snapshot[$property] != $this -> getSaveValue($property))
				return true;
		}

		return false;
	}

	/**
	 * Determine if the model or all the given property(ies) have remained the same.
	 *
	 * @param	string|string[]|null		$properties		Property name or a list of property names
	 * @return	bool
	 */
	public function isClean(string|array|null $properties = null) {
		return !$this -> isDirty($properties);
	}

	/**
	 * Increase value of specified property to the given amount for this model instance.
	 *
	 * @param	string		$key
	 * @param	int|float	$amount
	 */
	public function increase(string $key, int|float $amount = 1) {
		if (!$this -> isPropertyExists($key))
			throw new InvalidProperty($key, static::class);

		static::query()
			-> where(static::col("#"), $this -> getPrimaryValue())
			-> increase(static::col($key), $amount);

		$this -> {$key} += $amount;
		$this -> snapshot[$key] = $this -> getSaveValue($key);
		return $this;
	}

	/**
	 * Decrease value of specified property to the given amount for this model instance.
	 *
	 * @param	string		$key
	 * @param	int|float	$amount
	 */
	public function decrease(string $key, int|float $amount = 1) {
		return $this -> increase($key, -$amount);
	}

	/**
	 * Process the returned record from database.
	 *
	 * @param	object	The record object `$DB -> get_record()` API returned.
	 * @return	M
	 */
	public static function processRecord(stdClass $record) {

		// Use currently existing instance of this record if available to
		// save memory space and make sure we are working on the same thing.
		$instance = self::getInstance($record -> {static::$primaryKey});

		if (empty($instance))
			$instance = new static();

		static::normalizeMaps();
		$instance -> isPopulatingData = true;

		foreach (static::$fillables as $objKey => $dbKey) {
			$value = $record -> {$dbKey};

			$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
				&& !empty(static::$lazyloadMap[static::class][$objKey]);

			if ($hasLazyloadMapping) {
				// Update mapped value to attribute.
				if (!isset(self::$lazyloads[static::class]))
					self::$lazyloads[static::class] = array();

				$id = $instance -> getInstanceID();

				if (!isset(self::$lazyloads[static::class][$id]))
					self::$lazyloads[static::class][$id] = array();

				if (!empty(self::$lazyloads[static::class][$id][$objKey])) {
					// Lazyloaded value is the same with database. We don't need to touch this
					// property and invalidate lazyloaded data.
					if (self::$lazyloads[static::class][$id][$objKey] == $value)
						continue;

					$instance -> invalidateLazyloadProperty($objKey);
				}

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

		$instance -> isPopulatingData = false;
		$instance -> takeSnapshot();

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
	public static function processRecords(array $records) {
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
	protected static function getLazyloadMapName(string $name) {
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
	public function get(string $name, bool $sensitive = false, bool $safe = false) {
		// Getting ID from a instance inside this model (Eg. `$model -> parentID`).
		// Remove ID prefix and return the instance ID directly if loaded, else
		// return the lazyload mapping value.
		if ((str_ends_with($name, "ID") || str_ends_with($name, "Id")) && !property_exists($this, $name)) {
			$realName = preg_replace('/ID$/i', "", $name);
			$lazyloadMap = static::getLazyloadMapName($realName);

			if (!empty($lazyloadMap)) {
				if (empty($this -> {$lazyloadMap})) {
					$id = $this -> getInstanceID();
					$hasLazyloading = !empty(self::$lazyloads[static::class])
						&& !empty(self::$lazyloads[static::class][$id])
						&& array_key_exists($realName, self::$lazyloads[static::class][$id]);

					if (!$hasLazyloading)
						return null;

					// Cast: the value is the raw column as the driver read it,
					// and an id has to be an int here as it is everywhere
					// else, or it stops comparing against one.
					$stored = self::$lazyloads[static::class][$id][$realName];

					return is_numeric($stored) ? (int) $stored : $stored;
				} else if (!empty($this -> {$lazyloadMap})) {
					if ($this -> {$lazyloadMap} instanceof Model)
						return $this -> {$lazyloadMap} -> getPrimaryValue();
					else if ($this -> {$lazyloadMap} instanceof stdClass && !empty($this -> {$lazyloadMap} -> id))
						return (int) $this -> {$lazyloadMap} -> id;
				}

				return null;
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

		// The flag can be consumed while the typed property stays
		// uninitialized: the flag lives in static state keyed by instance, the
		// value on the object. Reading the property then throws "must not be
		// accessed before initialization", and the stored id is still known,
		// so it is resolved now instead.
		if (!$processLazyloadValue && $name !== $realName && !$this -> getReflection() -> getProperty($name) -> isInitialized($this))
			$processLazyloadValue = true;

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
			$reflection = $this -> getReflection();
			$property = $reflection -> getProperty($name);
			$attrs = $property -> getAttributes(SensitiveField::class);

			if (!empty($attrs))
				return null;
		}

		return ($safe && !isset($this -> {$name}))
			? null
			: $this -> {$name};
	}

	public function __get(string $name) {
		return $this -> get($name, true);
	}

	public function __set(string $name, $value) {
		$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
			&& !empty(static::$lazyloadMap[static::class][$name]);

		if ($hasLazyloadMapping) {
			static::$lazyloadWillProcess[static::class][$this -> getInstanceID()][$name] = false;
			unset(static::$lazyloads[static::class][$this -> getInstanceID()][$name]);

			$virtName = static::$lazyloadMap[static::class][$name];
			$this -> {$virtName} = $value;
			return;
		}

		$this -> {$name} = $value;
	}

	public function __isset(string $name) {
		// Checking isset on lazyloaded property via an Id prefix.
		if ((str_ends_with($name, "ID") || str_ends_with($name, "Id")) && !property_exists($this, $name))
			$name = preg_replace('/ID$/i', "", $name);

		$hasLazyloadMapping = isset(static::$lazyloadMap[static::class])
			&& !empty(static::$lazyloadMap[static::class][$name]);

		if ($hasLazyloadMapping) {
			$id = $this -> getInstanceID();

			if (empty(static::$lazyloadWillProcess[static::class][$id][$name])) {
				$lazyloadName = static::$lazyloadMap[static::class][$name];
				return isset($this -> {$lazyloadName});
			} else {
				return isset(self::$lazyloads[static::class][$id][$name]);
			}
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
		$out = array();

		static::normalizeMaps();
		$ref = new ReflectionClass($this);

		foreach (static::$fillables as $objKey => $dbKey) {
			if ($depth > 0) {
				$lazyloadMap = static::getLazyloadMapName($objKey);

				if ($lazyloadMap) {
					$prop = $ref -> getProperty($lazyloadMap);

					if ($this -> isModelProperty($prop)) {
						if ($depth <= 1) {
							// The id, not null: a shallow payload is meant to
							// carry a reference that can be followed, and the
							// id is known whether or not the relation loaded.
							$out[$objKey] = $this -> relationID($objKey);
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

	/**
	 * Return json serializable data.
	 *
	 * @param	int		$depth		Maximum depth we will return model instances. -1 to disable depth restriction.
	 * @return	array
	 */
	#[ReturnTypeWillChange]
	public function jsonSerialize(int $depth = -1) {
		$out = array();

		static::normalizeMaps();
		$ref = new ReflectionClass($this);

		foreach (static::$fillables as $objKey => $dbKey) {
			if ($depth > 0) {
				$lazyloadMap = static::getLazyloadMapName($objKey);

				if ($lazyloadMap) {
					$prop = $ref -> getProperty($lazyloadMap);

					if ($this -> isModelProperty($prop)) {
						if ($depth <= 1) {
							$out[$objKey] = $this -> relationID($objKey);
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

	public function __tostring(): string {
		return "[" . static::class . "({$this -> getPrimaryValue()})#{$this -> getInstanceID()}]";
	}

	public function __serialize(): array {
		if (empty($this -> {static::$primaryKey}))
			return array();

		return (array) $this -> toRecord();
	}

	public function __unserialize(array $data): void {
		if (empty($data))
			return;

		// TODO: Unserializing model instance will create duplicate! Need to find a better way.

		if (!self::getInstance($data[static::$primaryKey])) {
			$this -> {static::$primaryKey} = $data[static::$primaryKey];
			self::saveInstance($data[static::$primaryKey], $this);
		}

		$instance = self::processRecord((object) $data);

		if ($instance !== $this) {
			foreach ($instance as $key => $value)
				$this -> {$key} = $value;

			$this -> uniqueModelInstanceID = null;
			$sourceID = $instance -> getInstanceID();
			$targetID = $this -> getInstanceID();

			self::$lazyloadWillProcess[static::class][$targetID] = self::$lazyloadWillProcess[static::class][$sourceID];
			self::$lazyloads[static::class][$targetID] = self::$lazyloads[static::class][$sourceID];
		}
	}
}
