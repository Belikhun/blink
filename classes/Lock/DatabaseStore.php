<?php

namespace Blink\Lock;

/**
 * Locks kept in the `locks` table.
 *
 * The one store that reaches every host sharing a database, which is what a
 * lock guarding a queue or a scheduled entry needs.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseStore extends Store {
	const TABLE = "locks";

	public function acquire(string $name, string $token, int $ttl, string $owner): bool {
		global $DB;

		$now = time();

		// Clear the claim first if it has run out. Doing it as its own
		// statement keeps the insert below a single conditional write, and a
		// second process reaping the same row changes nothing.
		$DB -> raw(
			"DELETE FROM `" . static::TABLE . "` WHERE `name` = ? AND `expires` <= ?",
			array( $name, $now )
		);

		try {
			$DB -> raw(
				"INSERT INTO `" . static::TABLE . "` (`name`, `token`, `owner`, `acquired`, `expires`) VALUES (?, ?, ?, ?, ?)",
				array( $name, $token, $owner, $now, $now + $ttl )
			);
		} catch (\Throwable $e) {
			// The unique index on `name` is the arbiter: exactly one of the
			// processes racing here gets its insert in, and the rest read this
			// as someone else holding the lock.
			if ($DB -> isUniqueViolation($e))
				return false;

			throw $e;
		}

		return true;
	}

	public function release(string $name, string $token): bool {
		global $DB;

		$affected = $DB -> raw(
			"DELETE FROM `" . static::TABLE . "` WHERE `name` = ? AND `token` = ?",
			array( $name, $token )
		);

		return $affected > 0;
	}

	public function refresh(string $name, string $token, int $ttl): bool {
		global $DB;

		$affected = $DB -> raw(
			"UPDATE `" . static::TABLE . "` SET `expires` = ? WHERE `name` = ? AND `token` = ?",
			array( time() + $ttl, $name, $token )
		);

		return $affected > 0;
	}

	public function holder(string $name): ?array {
		global $DB;

		$rows = $DB -> raw(
			"SELECT * FROM `" . static::TABLE . "` WHERE `name` = ? AND `expires` > ?",
			array( $name, time() )
		);

		return empty($rows)
			? null
			: static::row($rows[0]);
	}

	public function all(): array {
		global $DB;

		$rows = $DB -> raw(
			"SELECT * FROM `" . static::TABLE . "` WHERE `expires` > ? ORDER BY `acquired`",
			array( time() )
		);

		$locks = array();

		foreach ($rows as $row) {
			$lock = static::row($row);
			$locks[$lock["name"]] = $lock;
		}

		return $locks;
	}

	public function forget(string $name): bool {
		global $DB;

		$affected = $DB -> raw(
			"DELETE FROM `" . static::TABLE . "` WHERE `name` = ?",
			array( $name )
		);

		return $affected > 0;
	}

	protected static function row(object $row): array {
		return array(
			"name" => (string) $row -> name,
			"token" => (string) $row -> token,
			"owner" => (string) $row -> owner,
			"acquired" => (int) $row -> acquired,
			"expires" => (int) $row -> expires
		);
	}
}
