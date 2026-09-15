<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\File;
use function Blink\convertSize;
use function Blink\relativeTime;
use function Blink\trimString;

/**
 * List stored files, newest first.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileList extends Command {
	public static string $usage = "file:list {--limit=20 : How many to show}"
		. " {--hash= : Only rows holding this content}"
		. " {--shared : Only content more than one row points at}";

	public static string $description = "List the stored files";

	public function run() {
		$limit = max((int) $this -> option("limit"), 1);
		$query = File::query() -> sort(File::col("id"), "DESC");

		if (($hash = $this -> option("hash")) !== null && $hash !== "")
			$query -> where(File::col("hash"), $hash);

		$total = $query -> count();
		$files = $query -> all(0, $limit);

		if (empty($files)) {
			$this -> info("No files.");
			return Console::EXIT_OK;
		}

		$store = File::store();
		$rows = array();
		$missing = 0;

		foreach ($files as $file) {
			$shared = $file -> shared();

			if ($this -> option("shared") && $shared < 2)
				continue;

			$held = $store -> exists($file -> hash);

			if (!$held)
				$missing++;

			$rows[] = array(
				(string) $file -> id,
				substr($file -> hash, 0, 16),
				trimString($file -> filename, 34),
				convertSize($file -> size),
				$file -> mimetype,
				$shared > 1 ? "x{$shared}" : "",
				$held ? "" : "missing",
				relativeTime($file -> created)
			);
		}

		if (empty($rows)) {
			$this -> info("Nothing matches.");
			return Console::EXIT_OK;
		}

		$this -> table(array( "id", "hash", "filename", "size", "type", "shared", "content", "when" ), $rows);
		$this -> line();
		$this -> comment("Showing " . count($rows) . " of {$total} file(s), store " . $store -> name() . ".");

		if ($missing > 0)
			$this -> warning("{$missing} row(s) point at content the store no longer holds. Run file:prune.");

		return Console::EXIT_OK;
	}
}
