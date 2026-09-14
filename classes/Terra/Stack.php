<?php

namespace Blink\Terra;

use Blink\Exception\CodingError;
use Blink\Exception\InvalidDefinition;
use Blink\Lock;

/**
 * Every layer blink and the application ship, in the order they apply.
 *
 * Discovery reads the layer directories; the `terra` table records what has
 * already run. The table is created here rather than by a layer of its own,
 * since nothing could record the layer that creates it.
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

	/**
	 * Layers already recorded as applied.
	 *
	 * @return	array<string, array{layer: string, batch: int, raised: int}>
	 */
	public function applied(): array {
		global $DB;

		$this -> ensureTable();
		$rows = $DB -> raw("SELECT * FROM `" . static::TABLE . "` ORDER BY `batch`, `id`");
		$applied = array();

		foreach ($rows as $row) {
			$applied[$row -> layer] = array(
				"layer" => (string) $row -> layer,
				"batch" => (int) $row -> batch,
				"raised" => (int) $row -> raised
			);
		}

		return $applied;
	}

	/**
	 * Layers on disk that have not run yet.
	 *
	 * @return	array<string, string>	identifier => absolute path
	 */
	public function pending(): array {
		return array_diff_key($this -> discover(), $this -> applied());
	}

	/**
	 * What every layer's state is, for `terra:status`.
	 *
	 * @return	array<int, array{layer: string, path: ?string, applied: bool, batch: ?int, raised: ?int, missing: bool}>
	 */
	public function status(): array {
		$discovered = $this -> discover();
		$applied = $this -> applied();
		$rows = array();

		foreach ($discovered as $identifier => $path) {
			$rows[] = array(
				"layer" => $identifier,
				"path" => $path,
				"applied" => isset($applied[$identifier]),
				"batch" => $applied[$identifier]["batch"] ?? null,
				"raised" => $applied[$identifier]["raised"] ?? null,
				"missing" => false
			);
		}

		// A layer recorded as applied whose file has gone is worth showing:
		// it usually means a deploy dropped a file the database still
		// reflects, and a revert of it is no longer possible.
		foreach ($applied as $identifier => $record) {
			if (isset($discovered[$identifier]))
				continue;

			$rows[] = array(
				"layer" => $identifier,
				"path" => null,
				"applied" => true,
				"batch" => $record["batch"],
				"raised" => $record["raised"],
				"missing" => true
			);
		}

		usort($rows, fn ($a, $b) => strcmp($a["layer"], $b["layer"]));
		return $rows;
	}

	/**
	 * Raise every pending layer, in one new batch.
	 *
	 * @param	int			$step	Stop after this many layers. Zero for all of them.
	 * @return	string[]	Identifiers raised.
	 */
	public function apply(int $step = 0): array {
		$pending = $this -> pending();

		if (empty($pending))
			return array();

		if ($step > 0)
			$pending = array_slice($pending, 0, $step, true);

		$batch = $this -> nextBatch();
		$raised = array();

		foreach ($pending as $identifier => $path) {
			$layer = $this -> load($identifier, $path);

			if ($layer -> shouldRun())
				$layer -> raise();

			$this -> record($identifier, $batch);
			$raised[] = $identifier;
		}

		return $raised;
	}

	/**
	 * Drop the most recent batch, newest layer first.
	 *
	 * @param	int			$step	How many batches to drop.
	 * @return	string[]	Identifiers dropped.
	 */
	public function revert(int $step = 1): array {
		$applied = $this -> applied();

		if (empty($applied))
			return array();

		$batches = array_values(array_unique(array_map(fn ($row) => $row["batch"], $applied)));
		rsort($batches);
		$targets = array_slice($batches, 0, max($step, 1));

		$identifiers = array();

		foreach ($applied as $identifier => $row) {
			if (in_array($row["batch"], $targets, true))
				$identifiers[] = $identifier;
		}

		rsort($identifiers);
		return $this -> dropLayers($identifiers);
	}

	/**
	 * Drop every applied layer, newest first.
	 *
	 * @return	string[]	Identifiers dropped.
	 */
	public function reset(): array {
		$identifiers = array_keys($this -> applied());
		rsort($identifiers);

		return $this -> dropLayers($identifiers);
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
		$layer = require $path;

		if (!is_object($layer) || !($layer instanceof Layer))
			throw new InvalidDefinition($identifier, Layer::class, $path);

		return $layer;
	}

	/**
	 * Create the table Terra records itself in, if it is not there yet.
	 */
	public function ensureTable(): void {
		global $DB;

		if (in_array(static::TABLE, $DB -> tables(), true))
			return;

		Schema::create(static::TABLE, function (Table $table) {
			$table -> id();
			$table -> string("layer", 191) -> unique();
			$table -> integer("batch");
			$table -> timestamp("raised");
		});
	}

	/**
	 * @param	string[]	$identifiers
	 * @return	string[]
	 */
	protected function dropLayers(array $identifiers): array {
		$discovered = $this -> discover();
		$dropped = array();

		foreach ($identifiers as $identifier) {
			$path = $discovered[$identifier] ?? null;

			if (empty($path)) {
				throw new CodingError(
					"Terra: layer <code>{$identifier}</code> is recorded as applied but its file is gone.",
					"Restore the file to revert it, or clear the row from the <code>" . static::TABLE . "</code> table by hand."
				);
			}

			$layer = $this -> load($identifier, $path);

			if (!$layer -> reversible()) {
				throw new CodingError(
					"Terra: layer <code>{$identifier}</code> cannot be reverted.",
					"It does not implement <code>drop()</code>."
				);
			}

			$layer -> drop();
			$this -> forget($identifier);
			$dropped[] = $identifier;
		}

		return $dropped;
	}

	protected function nextBatch(): int {
		global $DB;

		$this -> ensureTable();
		$rows = $DB -> raw("SELECT MAX(`batch`) AS `batch` FROM `" . static::TABLE . "`");

		return ((int) ($rows[0] -> batch ?? 0)) + 1;
	}

	protected function record(string $identifier, int $batch): void {
		global $DB;

		$DB -> raw(
			"INSERT INTO `" . static::TABLE . "` (`layer`, `batch`, `raised`) VALUES (?, ?, ?)",
			array( $identifier, $batch, time() )
		);
	}

	protected function forget(string $identifier): void {
		global $DB;

		$DB -> raw(
			"DELETE FROM `" . static::TABLE . "` WHERE `layer` = ?",
			array( $identifier )
		);
	}
}
