<?php

namespace Blink\Ticker\Orbit;

use Blink\Console;
use Blink\ErrorPage\Instance;
use Blink\Lock;
use Blink\Ticker;
use Blink\Ticker\Task;

/**
 * One pass over the recurring entries, deciding what is due and starting it.
 *
 * Claiming a minute is a single conditional update per entry:
 *
 * ```sql
 * UPDATE ticker_orbits SET last_minute = :stamp WHERE name = ? AND last_minute < :stamp
 * ```
 *
 * Zero rows changed means another host already claimed that minute, so the
 * entry runs exactly once per minute across as many hosts as are ticking, with
 * no lock involved.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Sweep {
	const TABLE = "ticker_orbits";

	const SKIPPED = "skipped";
	const CLAIMED = "claimed";
	const QUEUED = "queued";
	const RAN = "ran";
	const FAILED = "failed";
	const LOCKED = "locked";

	/**
	 * Run every due entry.
	 *
	 * @param	?int	$now	The moment to sweep for. Defaults to now.
	 * @return	array<int, array{entry: string, outcome: string, detail: ?string}>
	 */
	public function run(int $now = null): array {
		$now = $now ?? time();

		// One stamp for the whole pass. Taking it per entry would let a sweep
		// that straddles a minute boundary claim two different minutes, and
		// run some entries twice.
		$stamp = (int) floor($now / 60);
		$results = array();

		foreach (Ticker::orbit() -> entries() as $entry) {
			if (!$entry -> due($now))
				continue;

			if (!$this -> claimMinute($entry -> name, $stamp)) {
				$results[] = $this -> result($entry, static::SKIPPED, "another tick claimed this minute");
				continue;
			}

			$results[] = $this -> start($entry);
		}

		return $results;
	}

	/**
	 * Take this minute for an entry, if nobody else has.
	 */
	protected function claimMinute(string $name, int $stamp): bool {
		global $DB;

		$affected = $DB -> raw(
			"UPDATE `" . static::TABLE . "` SET `last_minute` = ?, `last_run` = ? WHERE `name` = ? AND `last_minute` < ?",
			array( $stamp, time(), $name, $stamp )
		);

		if ($affected > 0)
			return true;

		// No row to update, which is how an entry looks the first time it
		// comes round. The unique index decides the race if several hosts
		// notice at the same moment.
		$rows = $DB -> raw("SELECT `id` FROM `" . static::TABLE . "` WHERE `name` = ? LIMIT 1", array( $name ));

		if (!empty($rows))
			return false;

		try {
			$DB -> insert(static::TABLE, array(
				"name" => $name,
				"last_minute" => $stamp,
				"last_run" => time(),
				"last_outcome" => "",
				"failures" => 0
			));

			return true;
		} catch (\Throwable $e) {
			if ($DB -> isUniqueViolation($e))
				return false;

			throw $e;
		}
	}

	protected function start(Entry $entry): array {
		if ($entry -> detached)
			return $this -> queue($entry);

		if (!$entry -> alone)
			return $this -> invoke($entry);

		$lock = Lock::of("orbit:" . $entry -> name);

		// Still running from a previous turn. Skipping is the point of
		// `alone()`: the work is already happening.
		if (!$lock -> acquire($entry -> aloneFor))
			return $this -> result($entry, static::LOCKED, "a previous run is still going");

		try {
			return $this -> invoke($entry);
		} finally {
			$lock -> release();
		}
	}

	/**
	 * Hand the entry to the queue rather than running it here.
	 */
	protected function queue(Entry $entry): array {
		if ($entry -> kind === Entry::KIND_COMMAND) {
			Ticker::queue(new \Blink\Ticker\Task\CommandTask($entry -> target));
			return $this -> result($entry, static::QUEUED, null);
		}

		Ticker::queue($this -> task($entry));
		return $this -> result($entry, static::QUEUED, null);
	}

	protected function invoke(Entry $entry): array {
		try {
			if ($entry -> kind === Entry::KIND_COMMAND) {
				$code = Console::call(...$this -> parseCommand($entry -> target));

				return ($code === Console::EXIT_OK)
					? $this -> result($entry, static::RAN, null)
					: $this -> result($entry, static::FAILED, "exit code {$code}");
			}

			$this -> task($entry) -> run();
			return $this -> result($entry, static::RAN, null);
		} catch (\Throwable $e) {
			return $this -> fail($entry, $e);
		}
	}

	protected function task(Entry $entry): Task {
		return is_string($entry -> target)
			? new $entry->target()
			: clone $entry -> target;
	}

	/**
	 * @return	array{0: string, 1: string[]}
	 */
	protected function parseCommand(string $command): array {
		$tokens = preg_split("/\s+/", trim($command));
		$name = array_shift($tokens);

		return array( (string) $name, $tokens ?: array() );
	}

	/**
	 * File a failure without letting it stop the sweep.
	 *
	 * A tick runs from cron, where a non-zero exit becomes mail nobody reads.
	 * The failure goes into the entry's row and an error report instead.
	 */
	protected function fail(Entry $entry, \Throwable $e): array {
		global $DB;

		$reportId = null;

		try {
			$instance = Instance::fromException($e);
			$reportId = $instance -> id;
		} catch (\Throwable $reportError) {
			error_log("[blink][ticker] could not file a report for {$entry -> name}: {$reportError}");
		}

		$detail = get_class($e) . ": " . $e -> getMessage();

		$DB -> raw(
			"UPDATE `" . static::TABLE . "`
				SET `last_outcome` = ?, `last_error` = ?, `last_report` = ?, `failures` = `failures` + 1
				WHERE `name` = ?",
			array( static::FAILED, $detail, $reportId, $entry -> name )
		);

		return $this -> result($entry, static::FAILED, $detail);
	}

	/**
	 * @return	array{entry: string, outcome: string, detail: ?string}
	 */
	protected function result(Entry $entry, string $outcome, ?string $detail): array {
		global $DB;

		// A skip says nothing about the entry, only about this tick: another
		// one claimed the minute and recorded what its run did. Writing it
		// here would replace a real outcome with noise. A failure has already
		// been written by `fail()`, with its error and report alongside.
		$records = !in_array($outcome, array( static::SKIPPED, static::FAILED ), true);

		if ($records) {
			$DB -> raw(
				"UPDATE `" . static::TABLE . "` SET `last_outcome` = ?, `last_error` = NULL, `last_report` = NULL WHERE `name` = ?",
				array( $outcome, $entry -> name )
			);
		}

		return array(
			"entry" => $entry -> name,
			"outcome" => $outcome,
			"detail" => $detail
		);
	}
}
