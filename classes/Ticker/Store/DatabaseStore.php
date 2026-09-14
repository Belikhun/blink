<?php

namespace Blink\Ticker\Store;

use Blink\Ticker\Task;
use function Blink\getRelativePath;

/**
 * Queued tasks kept in `ticker_tasks`, with failures filed in
 * `ticker_failures`.
 *
 * Claiming takes three statements and no transaction. A candidate is read,
 * then a conditional update stamps it with a token, and only a runner whose
 * update actually changed a row owns the task. Two runners reading the same
 * candidate is fine: the database decides which update lands, and the loser
 * sees zero affected rows and moves on.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseStore extends Store {
	const TABLE = "ticker_tasks";

	const FAILURES = "ticker_failures";

	public function push(Task $task, string $queue, int $delay = 0): ?int {
		global $DB;

		$now = time();

		return (int) $DB -> insert(static::TABLE, array(
			"queue" => $queue,
			"payload" => serialize($task),
			"task_key" => $task -> key(),
			"attempts" => 0,
			"available" => $now + max($delay, 0),
			"reserved" => 0,
			"expires" => 0,
			"token" => null,
			"created" => $now
		));
	}

	public function pushUnique(Task $task, string $queue, int $delay = 0): ?int {
		global $DB;

		$key = $task -> key();

		if (empty($key))
			return $this -> push($task, $queue, $delay);

		// Only a task nobody is running counts as already queued. One being
		// worked on now cannot absorb work that arrived after it started.
		$existing = $DB -> raw(
			"SELECT `id` FROM `" . static::TABLE . "` WHERE `task_key` = ? AND `reserved` = 0 LIMIT 1",
			array( $key )
		);

		if (!empty($existing))
			return null;

		return $this -> push($task, $queue, $delay);
	}

	public function claim(string $queue, int $ttl): ?QueuedTask {
		global $DB;

		$now = time();

		// Oldest eligible first, including one whose claim has lapsed because
		// the runner holding it went away.
		$candidates = $DB -> raw(
			"SELECT * FROM `" . static::TABLE . "`
				WHERE `queue` = ? AND `available` <= ?
					AND (`reserved` = 0 OR `expires` <= ?)
				ORDER BY `available`, `id`
				LIMIT 1",
			array( $queue, $now, $now )
		);

		if (empty($candidates))
			return null;

		$id = (int) $candidates[0] -> id;
		$token = bin2hex(random_bytes(16));

		// The same condition again, this time as part of the write. Whoever's
		// update lands first makes the other's condition false.
		$affected = $DB -> raw(
			"UPDATE `" . static::TABLE . "`
				SET `reserved` = ?, `expires` = ?, `token` = ?, `attempts` = `attempts` + 1
				WHERE `id` = ? AND (`reserved` = 0 OR `expires` <= ?)",
			array( $now, $now + max($ttl, 1), $token, $id, $now )
		);

		if ($affected < 1)
			return null;

		$rows = $DB -> raw(
			"SELECT * FROM `" . static::TABLE . "` WHERE `id` = ? AND `token` = ?",
			array( $id, $token )
		);

		return empty($rows)
			? null
			: QueuedTask::fromRow($rows[0]);
	}

	public function release(QueuedTask $queued, int $delay = 0): bool {
		global $DB;

		$affected = $DB -> raw(
			"UPDATE `" . static::TABLE . "`
				SET `reserved` = 0, `expires` = 0, `token` = NULL, `available` = ?
				WHERE `id` = ? AND `token` = ?",
			array( time() + max($delay, 0), $queued -> id, $queued -> token )
		);

		return $affected > 0;
	}

	public function delete(QueuedTask $queued): bool {
		global $DB;

		// Guarded by the token: a runner that overran its claim must not
		// delete a task another runner has since taken over.
		$affected = $DB -> raw(
			"DELETE FROM `" . static::TABLE . "` WHERE `id` = ? AND `token` = ?",
			array( $queued -> id, $queued -> token )
		);

		return $affected > 0;
	}

	public function fail(QueuedTask $queued, \Throwable $e, string $reportId = null): void {
		global $DB;

		$DB -> insert(static::FAILURES, array(
			"queue" => $queued -> queue,
			"payload" => $queued -> payload,
			"task_key" => $queued -> key,
			"label" => $queued -> label(),
			"attempts" => $queued -> attempts,
			"error" => get_class($e) . ": " . $e -> getMessage(),
			"location" => getRelativePath($e -> getFile()) . ":" . $e -> getLine(),
			"report" => $reportId,
			"failed" => time()
		));

		$this -> delete($queued);
	}

	public function size(string $queue = null): int {
		global $DB;

		$rows = empty($queue)
			? $DB -> raw("SELECT COUNT(*) AS `total` FROM `" . static::TABLE . "`")
			: $DB -> raw("SELECT COUNT(*) AS `total` FROM `" . static::TABLE . "` WHERE `queue` = ?", array( $queue ));

		return (int) ($rows[0] -> total ?? 0);
	}

	public function forget(string $key): int {
		global $DB;

		return (int) $DB -> raw(
			"DELETE FROM `" . static::TABLE . "` WHERE `task_key` = ? AND `reserved` = 0",
			array( $key )
		);
	}

	public function pullForward(string $key, int $when): bool {
		global $DB;

		$affected = $DB -> raw(
			"UPDATE `" . static::TABLE . "`
				SET `available` = ?
				WHERE `task_key` = ? AND `reserved` = 0 AND `available` > ?",
			array( $when, $key, $when )
		);

		return $affected > 0;
	}

	public function clear(string $queue = null): int {
		global $DB;

		return empty($queue)
			? (int) $DB -> raw("DELETE FROM `" . static::TABLE . "`")
			: (int) $DB -> raw("DELETE FROM `" . static::TABLE . "` WHERE `queue` = ?", array( $queue ));
	}

	public function peek(string $queue = null, int $limit = 20): array {
		global $DB;

		$limit = max($limit, 1);

		$rows = empty($queue)
			? $DB -> raw("SELECT * FROM `" . static::TABLE . "` ORDER BY `available`, `id` LIMIT {$limit}")
			: $DB -> raw("SELECT * FROM `" . static::TABLE . "` WHERE `queue` = ? ORDER BY `available`, `id` LIMIT {$limit}", array( $queue ));

		return array_map(fn ($row) => QueuedTask::fromRow($row), $rows);
	}

	/**
	 * Tasks that ran out of attempts, newest first.
	 *
	 * @return	object[]
	 */
	public function failures(int $limit = 20): array {
		global $DB;

		$limit = max($limit, 1);

		return $DB -> raw("SELECT * FROM `" . static::FAILURES . "` ORDER BY `failed` DESC, `id` DESC LIMIT {$limit}");
	}

	/**
	 * Put a failed task back on its queue and take it off the failure list.
	 *
	 * @param	?int	$id		A single failure, or null for all of them.
	 * @return	int		How many were requeued.
	 */
	public function retry(int $id = null): int {
		global $DB;

		$failures = ($id === null)
			? $DB -> raw("SELECT * FROM `" . static::FAILURES . "`")
			: $DB -> raw("SELECT * FROM `" . static::FAILURES . "` WHERE `id` = ?", array( $id ));

		$now = time();
		$requeued = 0;

		foreach ($failures as $failure) {
			$DB -> insert(static::TABLE, array(
				"queue" => $failure -> queue,
				"payload" => $failure -> payload,
				"task_key" => $failure -> task_key,
				"attempts" => 0,
				"available" => $now,
				"reserved" => 0,
				"expires" => 0,
				"token" => null,
				"created" => $now
			));

			$DB -> raw("DELETE FROM `" . static::FAILURES . "` WHERE `id` = ?", array( $failure -> id ));
			$requeued++;
		}

		return $requeued;
	}

	/**
	 * Empty the failure list.
	 */
	public function flushFailures(): int {
		global $DB;
		return (int) $DB -> raw("DELETE FROM `" . static::FAILURES . "`");
	}
}
