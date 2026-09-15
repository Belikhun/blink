<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\File;
use function Blink\convertSize;

/**
 * Reconcile the file table against the store.
 *
 * Two things can drift apart. Content the store still holds that no row points
 * at, left by a bulk delete that went around {@see File::delete()}. And rows
 * pointing at content the store no longer holds, left by something reaching
 * into the store directly.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FilePrune extends Command {
	public static string $usage = "file:prune {--orphans : Delete content no row points at}"
		. " {--rows : Delete rows pointing at content that is gone}"
		. " {--f|force : Skip the confirmation}";

	public static string $description = "Reconcile stored files against the file table";

	public function run() {
		$store = File::store();
		$hashes = iterator_to_array($store -> hashes(), false);

		$this -> heading("store: " . $store -> name());

		// Held by the store, pointed at by nothing.
		$orphans = array();
		$freed = 0;

		foreach ($hashes as $hash) {
			if (File::query() -> where(File::col("hash"), $hash) -> exists())
				continue;

			$orphans[] = $hash;
			$size = $store -> size($hash);
			$freed += max($size, 0);
		}

		// Pointed at by a row, held by nothing.
		$held = array_flip($hashes);
		$dangling = array();

		foreach (File::query() -> sort(File::col("id"), "ASC") -> iterator() as $file) {
			if (isset($held[$file -> hash]) || $store -> exists($file -> hash))
				continue;

			$dangling[] = $file;
		}

		$this -> line(count($hashes) . " item(s) in the store, " . File::query() -> count() . " row(s) in the table.");
		$this -> line(count($orphans) . " orphaned item(s), " . convertSize($freed) . " on disk.");
		$this -> line(count($dangling) . " row(s) pointing at content that is gone.");

		if (empty($orphans) && empty($dangling)) {
			$this -> line();
			$this -> success("Nothing to reconcile.");
			return Console::EXIT_OK;
		}

		$doOrphans = (bool) $this -> option("orphans");
		$doRows = (bool) $this -> option("rows");

		if (!$doOrphans && !$doRows) {
			$this -> line();
			$this -> comment("Nothing was changed. Pass --orphans, --rows, or both.");
			return Console::EXIT_OK;
		}

		$this -> line();

		if ($doOrphans && !empty($orphans)) {
			if ($this -> option("force") || $this -> confirm("Delete " . count($orphans) . " orphaned item(s), freeing " . convertSize($freed) . "?", true)) {
				$deleted = 0;

				foreach ($orphans as $hash) {
					if ($store -> forget($hash))
						$deleted++;
				}

				$this -> success("Deleted {$deleted} orphaned item(s).");
			}
		}

		if ($doRows && !empty($dangling)) {
			if ($this -> option("force") || $this -> confirm("Delete " . count($dangling) . " row(s) whose content is gone?", true)) {
				$deleted = 0;

				foreach ($dangling as $file) {
					$file -> delete();
					$deleted++;
				}

				$this -> success("Deleted {$deleted} row(s).");
			}
		}

		return Console::EXIT_OK;
	}
}
