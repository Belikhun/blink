<?php

namespace Blink\Terra;

use Blink\DB\Database;
use Blink\DB\Exception\DatabaseConnectionFailed;
use Blink\Exception\CodingError;
use Blink\Exception\InvalidDefinition;
use Blink\Lock;
use Blink\Terra\Exception\LayerFailed;

/**
 * Every layer blink and the application ship, in the order they apply.
 *
 * Discovery reads the layer directories; the `terra` table records what has
 * already run. The table is created here rather than by a layer of its own,
 * since nothing could record the layer that creates it.
 *
 * Each database instance keeps its own `terra` table, holding the layers
 * raised on it. A batch number is shared across them: one apply run is one
 * batch wherever its layers landed, so a revert takes the whole run back.
 *
 * A layer is in one of these states:
 *
 * - `applied`: its target's ledger records it.
 * - `pending`: its target is reachable and it has not run there.
 * - `waiting`: its target is an instance the site has not registered. The
 *   layer sits out until one is, and the rest of the stack goes on without
 *   it; a module shipping layers for an optional database must not stop a
 *   site that does without one.
 * - `unreachable`: its target is registered but could not be connected.
 * - `moved`: a ledger row on an instance the layer no longer targets, from
 *   before its `database()` changed. It can be reverted there.
 * - `missing`: a ledger row whose layer file is gone.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Stack {
	const TABLE = "terra";

	const LOCK = "terra";

	const APPLIED = "applied";
	const PENDING = "pending";
	const WAITING = "waiting";
	const UNREACHABLE = "unreachable";
	const MOVED = "moved";
	const MISSING = "missing";

	/**
	 * Layers loaded from disk this process, keyed by path and content hash.
	 * A layer file is read once; the status listing alone would otherwise
	 * require every one of them several times over. The hash is what lets a
	 * file edited in the same process be read again.
	 *
	 * @var array<string, Layer>
	 */
	protected array $loaded = array();

	/**
	 * Instances that could not be connected this process, with the reason.
	 * Tried once rather than on every ledger read: a dead server times out
	 * slowly, and the answer does not change within one command.
	 *
	 * @var array<string, string>
	 */
	protected array $unreachable = array();

	/**
	 * Directories layers are read from, blink's first.
	 *
	 * @return	string[]
	 */
	public function paths(): array {
		$paths = array_merge(
			array( CORE_ROOT . "/db/terra", BASE_PATH . "/db/terra" ),
			\CONFIG::$TERRA_PATHS
		);

		$unique = array();

		foreach ($paths as $path) {
			$path = rtrim(str_replace("\\", "/", $path), "/");

			if (!in_array($path, $unique, true))
				$unique[] = $path;
		}

		return $unique;
	}

	/**
	 * Every layer on disk, keyed by identifier and sorted by it.
	 *
	 * The identifier is the file name without its extension, which is what
	 * makes the timestamp prefix the ordering.
	 *
	 * @return	array<string, string>	identifier => absolute path
	 */
	public function discover(): array {
		$layers = array();

		foreach ($this -> paths() as $path) {
			foreach (glob("{$path}/*.php") as $file) {
				$identifier = pathinfo($file, PATHINFO_FILENAME);

				if (isset($layers[$identifier])) {
					throw new CodingError(
						"Terra: two layers share the identifier <code>{$identifier}</code>.",
						"Found in <code>{$layers[$identifier]}</code> and <code>{$file}</code>. Rename one of them."
					);
				}

				$layers[$identifier] = str_replace("\\", "/", $file);
			}
		}

		ksort($layers);
		return $layers;
	}


	//* ===========================================================
	//*  Instances
	//* ===========================================================

	/**
	 * Every instance whose ledger can be read, keyed by name. One that
	 * cannot be connected is noted in {@see Stack::unreachable()} and left
	 * out, so an outage on an optional database does not stop the schema
	 * work on the others.
	 *
	 * @return	array<string, Database>
	 */
	public function databases(): array {
		$reachable = array();

		foreach (Database::names() as $name) {
			if (isset($this -> unreachable[$name]))
				continue;

			try {
				$reachable[$name] = Database::get($name);
			} catch (DatabaseConnectionFailed $e) {
				$this -> unreachable[$name] = strip_tags($e -> getMessage());
			}
		}

		return $reachable;
	}

	/**
	 * Instances that could not be connected, with the reason.
	 *
	 * @return	array<string, string>
	 */
	public function unreachable(): array {
		$this -> databases();
		return $this -> unreachable;
	}

	/**
	 * Forget which instances failed to connect, so the next read tries them
	 * again.
	 */
	public function retry(): void {
		$this -> unreachable = array();
	}

	/**
	 * The name of the instance a layer runs on, as it declares it.
	 */
	public function targetName(Layer $layer): string {
		return $layer -> database() ?? Database::DEFAULT;
	}

	/**
	 * Whether a layer's target can take it right now: {@see Stack::PENDING}
	 * when it can, otherwise {@see Stack::WAITING} or
	 * {@see Stack::UNREACHABLE} with the reason.
	 *
	 * @return	array{0: string, 1: ?string}	State and note.
	 */
	public function availability(string $name): array {
		if (!Database::has($name))
			return array( static::WAITING, "instance {$name} is not registered" );

		$this -> databases();

		if (isset($this -> unreachable[$name]))
			return array( static::UNREACHABLE, $this -> unreachable[$name] );

		return array( static::PENDING, null );
	}


	//* ===========================================================
	//*  Ledgers
	//* ===========================================================

	/**
	 * Every ledger row on every reachable instance.
	 *
	 * @return	array<string, array<string, array{layer: string, batch: int, raised: int, database: string}>>	identifier => instance => row
	 */
	public function ledgers(): array {
		$rows = array();

		foreach ($this -> databases() as $name => $database) {
			foreach ($this -> ledger($database) as $identifier => $row)
				$rows[$identifier][$name] = $row;
		}

		return $rows;
	}

	/**
	 * The ledger rows of one instance, keyed by layer. An instance without
	 * the table has nothing recorded, and reading it does not create one.
	 *
	 * @return	array<string, array{layer: string, batch: int, raised: int, database: string}>
	 */
	protected function ledger(Database $database): array {
		if (!in_array(static::TABLE, $database -> tables(), true))
			return array();

		$rows = $database -> raw("SELECT * FROM `" . static::TABLE . "` ORDER BY `batch`, `id`");
		$ledger = array();

		foreach ($rows as $row) {
			$ledger[$row -> layer] = array(
				"layer" => (string) $row -> layer,
				"batch" => (int) $row -> batch,
				"raised" => (int) $row -> raised,
				"database" => $database -> name
			);
		}

		return $ledger;
	}

	/**
	 * What every layer's state is, for `terra:status` and everything else
	 * here. One row per layer file, plus one per ledger row that no file or
	 * current target accounts for.
	 *
	 * @param	?string		$database	Only rows on this instance.
	 * @return	array<int, array{layer: string, path: ?string, database: string, state: string, applied: bool, batch: ?int, raised: ?int, missing: bool, note: ?string}>
	 */
	public function status(?string $database = null): array {
		$discovered = $this -> discover();
		$ledgers = $this -> ledgers();
		$rows = array();

		foreach ($discovered as $identifier => $path) {
			$target = $this -> targetName($this -> load($identifier, $path));
			$record = $ledgers[$identifier][$target] ?? null;

			if ($record !== null) {
				$rows[] = $this -> row($identifier, $path, $target, static::APPLIED, $record, null);
			} else {
				[$state, $note] = $this -> availability($target);
				$rows[] = $this -> row($identifier, $path, $target, $state, null, $note);
			}

			// Rows on instances the layer no longer targets.
			foreach ($ledgers[$identifier] ?? array() as $name => $record) {
				if ($name !== $target)
					$rows[] = $this -> row($identifier, $path, $name, static::MOVED, $record, "now targets {$target}");
			}
		}

		// A layer recorded as applied whose file has gone is worth showing:
		// it usually means a deploy dropped a file the database still
		// reflects, and a revert of it is no longer possible.
		foreach ($ledgers as $identifier => $perInstance) {
			if (isset($discovered[$identifier]))
				continue;

			foreach ($perInstance as $name => $record)
				$rows[] = $this -> row($identifier, null, $name, static::MISSING, $record, "the layer file is gone");
		}

		if ($database !== null)
			$rows = array_filter($rows, fn ($row) => $row["database"] === $database);

		usort($rows, fn ($a, $b) => [$a["layer"], $a["database"]] <=> [$b["layer"], $b["database"]]);
		return array_values($rows);
	}

	protected function row(string $identifier, ?string $path, string $database, string $state, ?array $record, ?string $note): array {
		return array(
			"layer" => $identifier,
			"path" => $path,
			"database" => $database,
			"state" => $state,
			"applied" => $state === static::APPLIED,
			"batch" => $record["batch"] ?? null,
			"raised" => $record["raised"] ?? null,
			"missing" => $state === static::MISSING,
			"note" => $note
		);
	}

	/**
	 * Layers recorded on their current target.
	 *
	 * @return	array<string, array>	identifier => status row
	 */
	public function applied(?string $database = null): array {
		$applied = array();

		foreach ($this -> status($database) as $row) {
			if ($row["state"] === static::APPLIED)
				$applied[$row["layer"]] = $row;
		}

		return $applied;
	}

	/**
	 * Layers that can run now: on disk, not yet on their target, and the
	 * target reachable.
	 *
	 * @return	array<string, string>	identifier => absolute path
	 */
	public function pending(?string $database = null): array {
		$pending = array();

		foreach ($this -> status($database) as $row) {
			if ($row["state"] === static::PENDING)
				$pending[$row["layer"]] = $row["path"];
		}

		return $pending;
	}

	/**
	 * Layers that cannot run now because of their target: waiting for an
	 * instance to be registered, or for one to come back.
	 *
	 * @return	array<string, array>	identifier => status row
	 */
	public function blocked(?string $database = null): array {
		$blocked = array();

		foreach ($this -> status($database) as $row) {
			if ($row["state"] === static::WAITING || $row["state"] === static::UNREACHABLE)
				$blocked[$row["layer"]] = $row;
		}

		return $blocked;
	}


	//* ===========================================================
	//*  Raising and dropping
	//* ===========================================================

	/**
	 * Raise every layer that can run, in one new batch. Layers whose target
	 * is waiting or unreachable are left where they are; `terra:apply`
	 * reports them.
	 *
	 * @param	int			$step		Stop after this many layers. Zero for all of them.
	 * @param	?string		$database	Only layers targeting this instance.
	 * @return	string[]	Identifiers raised.
	 */
	public function apply(int $step = 0, ?string $database = null): array {
		$pending = $this -> pending($database);

		if (empty($pending))
			return array();

		if ($step > 0)
			$pending = array_slice($pending, 0, $step, true);

		$batch = $this -> nextBatch();
		$raised = array();

		foreach ($pending as $identifier => $path) {
			$layer = $this -> load($identifier, $path);
			$target = Database::get($this -> targetName($layer));

			if ($layer -> shouldRun()) {
				try {
					Schema::on($target, fn () => $layer -> raise());
				} catch (\Throwable $e) {
					throw new LayerFailed($identifier, $target -> name, "raise", $e);
				}
			}

			$this -> record($identifier, $batch, $target);
			$raised[] = $identifier;
		}

		return $raised;
	}

	/**
	 * Drop the most recent batches, newest layer first, on every instance
	 * their rows are on.
	 *
	 * @param	int			$step		How many batches to drop.
	 * @param	?string		$database	Only this instance's ledger, and the batches it knows.
	 * @return	string[]	Identifiers dropped.
	 */
	public function revert(int $step = 1, ?string $database = null): array {
		$rows = $this -> ledgerRows($database);

		if (empty($rows))
			return array();

		$batches = array_values(array_unique(array_map(fn ($row) => $row["batch"], $rows)));
		rsort($batches);
		$targets = array_slice($batches, 0, max($step, 1));

		$rows = array_filter($rows, fn ($row) => in_array($row["batch"], $targets, true));
		return $this -> dropRows($rows);
	}

	/**
	 * Drop every recorded layer, newest first.
	 *
	 * @param	?string		$database	Only this instance's ledger.
	 * @return	string[]	Identifiers dropped.
	 */
	public function reset(?string $database = null): array {
		return $this -> dropRows($this -> ledgerRows($database));
	}

	/**
	 * Remove a layer's ledger rows without touching the schema.
	 *
	 * For a row whose layer file is gone, or a database whose tables were
	 * replaced behind the ledger's back by a restored dump. The layer counts
	 * as pending again afterwards.
	 *
	 * @param	?string		$database	Only this instance's ledger, every reachable one when omitted.
	 * @return	string[]	Names of the instances a row was removed from.
	 */
	public function forgetRows(string $identifier, ?string $database = null): array {
		$removed = array();

		foreach ($this -> databases() as $name => $instance) {
			if ($database !== null && $name !== $database)
				continue;

			if (!in_array(static::TABLE, $instance -> tables(), true))
				continue;

			if ($this -> forget($identifier, $instance) > 0)
				$removed[] = $name;
		}

		return $removed;
	}

	/**
	 * Every ledger row on every reachable instance, newest batch and layer
	 * first: the order to drop them in.
	 *
	 * @return	array<int, array{layer: string, batch: int, raised: int, database: string}>
	 */
	protected function ledgerRows(?string $database): array {
		$rows = array();

		foreach ($this -> ledgers() as $perInstance) {
			foreach ($perInstance as $name => $row) {
				if ($database === null || $name === $database)
					$rows[] = $row;
			}
		}

		usort($rows, fn ($a, $b) => [$b["batch"], $b["layer"]] <=> [$a["batch"], $a["layer"]]);
		return $rows;
	}

	/**
	 * Take the lock that serialises schema work on this host.
	 *
	 * A file lock rather than a database one, since the table the database
	 * store needs is itself created by a layer. That also means it only
	 * reaches this host: a deployment spread across several has to apply at
	 * deploy time instead.
	 */
	public function lock(): Lock {
		return Lock::file(static::LOCK);
	}

	/**
	 * Load a layer file and check it returned what it should have.
	 */
	public function load(string $identifier, string $path): Layer {
		$key = $path . ":" . md5_file($path);

		if (isset($this -> loaded[$key]))
			return $this -> loaded[$key];

		$layer = require $path;

		if (!is_object($layer) || !($layer instanceof Layer))
			throw new InvalidDefinition($identifier, Layer::class, $path);

		$this -> loaded[$key] = $layer;
		return $layer;
	}

	/**
	 * Create the table Terra records itself in on an instance, if it is not
	 * there yet.
	 */
	public function ensureTable(?Database $database = null): void {
		$database = $database ?? Database::default();

		if (in_array(static::TABLE, $database -> tables(), true))
			return;

		Schema::on($database, function () {
			Schema::create(static::TABLE, function (Table $table) {
				$table -> id();
				$table -> string("layer", 191) -> unique();
				$table -> integer("batch");
				$table -> timestamp("raised");
			});
		});
	}

	/**
	 * Drop layers on the instance each was raised on, which the ledger row
	 * remembers even if the layer has since been pointed elsewhere.
	 *
	 * @param	array<int, array{layer: string, database: string}>	$rows	Ledger rows, in the order to drop.
	 * @return	string[]
	 */
	protected function dropRows(array $rows): array {
		$discovered = $this -> discover();
		$dropped = array();

		foreach ($rows as $row) {
			$identifier = $row["layer"];
			$path = $discovered[$identifier] ?? null;

			if (empty($path)) {
				throw new CodingError(
					"Terra: layer <code>{$identifier}</code> is recorded as applied on <code>{$row["database"]}</code> but its file is gone.",
					"Restore the file to revert it, or drop the row with <code>terra:forget {$identifier} --database={$row["database"]}</code>."
				);
			}

			$layer = $this -> load($identifier, $path);

			if (!$layer -> reversible()) {
				throw new CodingError(
					"Terra: layer <code>{$identifier}</code> cannot be reverted.",
					"It does not implement <code>drop()</code>."
				);
			}

			$database = Database::get($row["database"]);

			try {
				Schema::on($database, fn () => $layer -> drop());
			} catch (\Throwable $e) {
				throw new LayerFailed($identifier, $database -> name, "drop", $e);
			}

			$this -> forget($identifier, $database);
			$dropped[] = $identifier;
		}

		return $dropped;
	}

	/**
	 * One more than the highest batch on any ledger that can be read.
	 */
	protected function nextBatch(): int {
		$highest = 0;

		foreach ($this -> ledgers() as $perInstance) {
			foreach ($perInstance as $row)
				$highest = max($highest, $row["batch"]);
		}

		return $highest + 1;
	}

	protected function record(string $identifier, int $batch, Database $database): void {
		$this -> ensureTable($database);

		$database -> raw(
			"INSERT INTO `" . static::TABLE . "` (`layer`, `batch`, `raised`) VALUES (?, ?, ?)",
			array( $identifier, $batch, time() )
		);
	}

	/**
	 * @return	int		Rows removed.
	 */
	protected function forget(string $identifier, Database $database): int {
		return (int) $database -> raw(
			"DELETE FROM `" . static::TABLE . "` WHERE `layer` = ?",
			array( $identifier )
		);
	}
}
