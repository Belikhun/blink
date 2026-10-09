<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;

/**
 * Raise every pending schema layer.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraApply extends Command {
	public static string $usage = "terra:apply {--step= : Stop after this many layers}"
		. " {--database= : Only layers targeting this instance}"
		. " {--strict : Fail when a layer is held back by an instance that is missing or unreachable}"
		. " {--f|force : Skip the confirmation}";

	public static string $description = "Raise pending schema layers";

	public function run() {
		$database = $this -> option("database") ?: null;
		$pending = Terra::pending($database);
		$blocked = Terra::blocked($database);
		$targets = array();

		foreach (Terra::status($database) as $row) {
			if ($row["state"] === "pending")
				$targets[$row["layer"]] = $row["database"];
		}

		foreach (Terra::unreachable() as $name => $reason)
			$this -> error("Instance {$name} could not be reached: {$reason}");

		// Reported before anything is raised, so a deploy sees what is held
		// back whether or not it goes on.
		foreach ($blocked as $identifier => $row)
			$this -> warning("{$row["state"]}  {$identifier}  [{$row["database"]}]  {$row["note"]}");

		if (!empty($blocked) && $this -> option("strict")) {
			$this -> error(count($blocked) . " layer(s) cannot run. Nothing was changed.");
			return Console::EXIT_FAILURE;
		}

		if (empty($pending)) {
			$this -> info(empty($blocked)
				? "Nothing to raise, the schema is up to date."
				: "Nothing to raise that can run now.");

			return Console::EXIT_OK;
		}

		$this -> line("Pending layers:");

		foreach (array_keys($pending) as $identifier)
			$this -> line("  {$identifier}  [{$targets[$identifier]}]");

		$this -> line();

		if (!$this -> option("force") && !$this -> confirm("Raise " . count($pending) . " layer(s)?", true)) {
			$this -> comment("Nothing was changed.");
			return Console::EXIT_OK;
		}

		$raised = Terra::apply((int) ($this -> option("step") ?? 0), $database);

		foreach ($raised as $identifier)
			$this -> success("raised  {$identifier}  [{$targets[$identifier]}]");

		$this -> line();
		$this -> success(count($raised) . " layer(s) raised.");

		return Console::EXIT_OK;
	}
}
