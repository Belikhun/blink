<?php

namespace Blink\Trace;

use stdClass;
use Blink\DB;
use Blink\Exception\BaseException;
use Blink\Exception\CodingError;
use Blink\Model;
use Blink\Model\UserBase;
use Blink\Session;
use function Blink\getClientIP;

/**
 * Turn entry fields into rows and write them.
 *
 * Writes are deferred: {@see write()} resolves everything that depends on the
 * request (actor, client address, time) right away but queues the row rather
 * than inserting it. {@see flush()} drains the queue in one insert, from the
 * shutdown handler once the response is out, so recording costs the request
 * nothing.
 *
 * Accepted keys: `component` and `message` (both required), `level`, `mode`,
 * `scope`, `field`, `actor`, `source` or `sourceClass` + `sourceID`, `target`
 * or `targetClass` + `targetID`, `payload`, `created`.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Writer {
	/**
	 * Queue length that forces a write. A runner or a tick that records
	 * thousands of entries would otherwise hold them all until it exits, and
	 * lose the lot to a hard kill.
	 */
	protected const FLUSH_THRESHOLD = 200;

	/**
	 * Rows ready to insert, as {@see Entry::toRecord()} shapes them.
	 *
	 * @var stdClass[]
	 */
	protected array $deferred = array();

	public function __construct(
		protected LevelRegistry $levels,
		protected ModeRegistry $modes
	) {}

	public function write(array $entry): Entry {
		$record = $this -> build($entry);

		$this -> deferred[] = $record -> toRecord(primary: false);

		if (count($this -> deferred) >= static::FLUSH_THRESHOLD)
			$this -> flush();

		return $record;
	}

	/**
	 * Entries waiting to be written.
	 */
	public function pending(): int {
		return count($this -> deferred);
	}

	/**
	 * An in-memory entry with the request-dependent fields resolved. It has no
	 * id: nothing is written until {@see flush()}.
	 */
	protected function build(array $entry): Entry {
		$component = trim((string) ($entry["component"] ?? ""));
		$message = trim((string) ($entry["message"] ?? ""));

		if ($component === "")
			throw new CodingError("A trace entry needs a component.");

		if ($message === "")
			throw new CodingError("A trace entry needs a message.");

		list($sourceClass, $sourceID) = $this -> resolveModelRef($entry, "source");
		list($targetClass, $targetID) = $this -> resolveModelRef($entry, "target");

		return Entry::create(array(
			"actor" => $this -> normalizeActor($entry["actor"] ?? null),
			"actorIP" => $this -> detectClientIP(),
			"component" => mb_substr($component, 0, 64),
			"scope" => $this -> normalizeScope($entry["scope"] ?? null),
			"field" => $this -> normalizeField($entry["field"] ?? null),
			"mode" => $this -> modes -> normalize($entry["mode"] ?? null),
			"level" => $this -> levels -> normalize($entry["level"] ?? null),
			"message" => mb_substr($message, 0, 255),
			"sourceClass" => $sourceClass,
			"sourceID" => $sourceID,
			"targetClass" => $targetClass,
			"targetID" => $targetID,
			"payload" => $entry["payload"] ?? null,
			"created" => intval($entry["created"] ?? time())
		));
	}

	/**
	 * Write every queued entry in one insert and empty the queue.
	 *
	 * @return	int		Entries written.
	 */
	public function flush(): int {
		if (empty($this -> deferred))
			return 0;

		// Detached first, so an entry recorded while the insert runs queues
		// for the next flush rather than being lost or written twice.
		$records = $this -> deferred;
		$this -> deferred = array();

		try {
			DB::inserts(Entry::$table, $records);
		} catch (\Throwable $e) {
			$this -> flushIndividually($records, $e);
		}

		return count($records);
	}

	/**
	 * One row at a time, so a single bad row loses only itself. Failures go
	 * to the server log: recording them here would only queue them again
	 * during shutdown.
	 *
	 * @param	stdClass[]	$records
	 */
	protected function flushIndividually(array $records, \Throwable $batchError): void {
		error_log("[blink][trace] batched insert failed, writing one row at a time: {$batchError}");

		foreach ($records as $record) {
			try {
				DB::insert(Entry::$table, $record);
			} catch (\Throwable $e) {
				error_log("[blink][trace] dropped an entry after its insert failed: {$e}");
			}
		}
	}

	/**
	 * A model reference given either as an instance under `$prefix` or as a
	 * `{$prefix}Class` and `{$prefix}ID` pair.
	 *
	 * @return	array{0: ?string, 1: ?int}
	 */
	protected function resolveModelRef(array $entry, string $prefix): array {
		$model = $entry[$prefix] ?? null;

		if ($model instanceof Model)
			return array( get_class($model), intval($model -> getPrimaryValue()) );

		$class = $entry["{$prefix}Class"] ?? null;
		$id = $entry["{$prefix}ID"] ?? null;

		return array(
			$class !== null ? ltrim((string) $class, "\\") : null,
			$id !== null ? intval($id) : null
		);
	}

	/**
	 * The actor as a user, the signed-in one when none was named. An
	 * application with no user class records nobody.
	 */
	protected function normalizeActor(int|UserBase|null $actor): ?UserBase {
		if ($actor === null)
			return (Session::$user instanceof UserBase) ? Session::$user : null;

		if ($actor instanceof UserBase)
			return $actor;

		try {
			$userClass = \Blink::userClass();
			return $userClass::getByID($actor, Model::IGNORE_MISSING) ?: null;
		} catch (BaseException $e) {
			return null;
		}
	}

	protected function normalizeScope(?string $scope): ?string {
		$scope = ($scope !== null) ? trim($scope) : null;

		return ($scope === null || $scope === "")
			? null
			: mb_substr($scope, 0, 128);
	}

	protected function normalizeField(?string $field): ?string {
		$field = ($field !== null) ? trim($field) : null;

		return ($field === null || $field === "")
			? null
			: mb_substr($field, 0, 32);
	}

	protected function detectClientIP(): ?string {
		$ip = trim((string) getClientIP());

		if ($ip === "" || strtoupper($ip) === "UNKNOWN")
			return null;

		// A forwarded chain lists the client first.
		list($ip) = explode(",", $ip, 2);
		$ip = trim($ip);

		return ($ip === "")
			? null
			: substr($ip, 0, 45);
	}
}
