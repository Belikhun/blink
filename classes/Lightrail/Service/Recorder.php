<?php

namespace Blink\Lightrail\Service;

use Blink\DB;
use Blink\Lightrail\Event\BaseEvent;
use Blink\Lightrail\ExecutionMode;
use Blink\Lightrail\Model\DispatchLog;
use Blink\Lightrail\Model\HandlerLog;
use Blink\Lightrail\Registry\HandlerDescriptor;
use Blink\Model;

/**
 * Keeps the trail of what was dispatched and how each handler fared.
 *
 * The trail is write-heavy: one row per event plus one per handler, each
 * updated again as the handler finishes. A request that saves twenty models
 * with three observers apiece produces a great many tiny writes.
 *
 * So they are buffered in memory and written in a handful of batched inserts
 * at shutdown, after the response has gone out. The exception is a dispatch
 * with a handler that runs in another process: that one is written
 * immediately, because the runner reads it back from the database and cannot
 * wait for this request to end.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Recorder {
	/**
	 * Write finished dispatches out once this many are buffered, so a runner
	 * that dispatches for hours does not grow the buffer without bound.
	 */
	protected const FLUSH_THRESHOLD = 200;

	/**
	 * Buffered dispatches, keyed by object id.
	 *
	 * @var array<int, DispatchLog>
	 */
	protected array $bufferedDispatches = array();

	/**
	 * Buffered handler rows, grouped by their dispatch's object id.
	 *
	 * @var array<int, HandlerLog[]>
	 */
	protected array $bufferedHandlers = array();

	/**
	 * Handler object id to dispatch object id, so a handler can tell whether
	 * it is still buffered.
	 *
	 * @var array<int, int>
	 */
	protected array $handlerDispatch = array();

	/**
	 * Open a dispatch row for an emission.
	 *
	 * @param	HandlerDescriptor[]		$handlers
	 * @param	bool					$defer		Buffer it rather than writing now.
	 */
	public function queueDispatch(BaseEvent $event, string $mode, array $handlers = array(), bool $defer = false): DispatchLog {
		$total = count($handlers);

		$dispatch = DispatchLog::create(array(
			// Random and unique, which is what lets a batched insert find the
			// ids it just generated without asking for them one at a time.
			"correlationID" => bin2hex(random_bytes(10)),
			"eventClass" => $event::class,
			"eventPayload" => $event -> toArray(),
			"dispatchMode" => $mode,
			"status" => DispatchLog::STATUS_PENDING,
			"totalHandlers" => $total,
			"pendingHandlers" => $total,
			"completedHandlers" => 0,
			"successHandlers" => 0,
			"errorHandlers" => 0,
			"duration" => 0
		));

		if (!$defer)
			return $dispatch -> save();

		$now = time();
		$dispatch -> created = $now;
		$dispatch -> updated = $now;

		$this -> bufferedDispatches[spl_object_id($dispatch)] = $dispatch;
		$this -> bufferedHandlers[spl_object_id($dispatch)] = array();

		return $dispatch;
	}

	/**
	 * Open a pending row for each handler of a dispatch.
	 *
	 * @param	HandlerDescriptor[]		$handlers
	 * @return	HandlerLog[]
	 */
	public function queueHandlers(DispatchLog $dispatch, array $handlers): array {
		$rows = array();
		$deferred = $this -> isDeferred($dispatch);
		$now = time();

		foreach ($handlers as $handler) {
			$log = HandlerLog::create(array(
				// A buffered dispatch has no id yet; the real one is stamped
				// in when the rows are written.
				"dispatchID" => $deferred ? 0 : $dispatch -> id,
				"correlationID" => $dispatch -> correlationID,
				"eventClass" => $dispatch -> eventClass,
				"handler" => $handler -> callable,
				"handlerKey" => $handler -> key,
				"module" => $handler -> module,
				"observer" => $handler -> observer,
				"executionMode" => $handler -> executionMode,
				"status" => HandlerLog::STATUS_PENDING,
				"attemptNumber" => 1,
				"retryOf" => null,
				"duration" => 0
			));

			if ($deferred) {
				$log -> created = $now;
				$log -> updated = $now;
				$this -> bufferedHandlers[spl_object_id($dispatch)][] = $log;
				$this -> handlerDispatch[spl_object_id($log)] = spl_object_id($dispatch);
			} else {
				$log -> save();
			}

			$rows[] = $log;
		}

		if ($deferred && count($this -> bufferedDispatches) >= static::FLUSH_THRESHOLD)
			$this -> flushResolved();

		return $rows;
	}

	/**
	 * @param	HandlerDescriptor[]		$handlers
	 */
	public function ensureQueuedHandlers(DispatchLog $dispatch, array $handlers): void {
		$existing = HandlerLog::query()
			-> where(HandlerLog::col("dispatchID"), $dispatch -> id)
			-> first();

		if ($existing)
			return;

		$this -> queueHandlers($dispatch, $handlers);
		$this -> syncDispatch($dispatch);
	}

	/**
	 * Open another attempt at a handler that already failed.
	 */
	public function queueRetryHandler(DispatchLog $dispatch, HandlerLog $handler): HandlerLog {
		$attempt = 1;

		$previous = HandlerLog::query()
			-> where(HandlerLog::col("dispatchID"), $dispatch -> id)
			-> where(HandlerLog::col("handlerKey"), $handler -> handlerKey)
			-> all();

		foreach ($previous as $row) {
			if ($row -> attemptNumber >= $attempt)
				$attempt = $row -> attemptNumber + 1;
		}

		$retry = HandlerLog::create(array(
			"dispatchID" => $dispatch -> id,
			"correlationID" => $dispatch -> correlationID,
			"eventClass" => $dispatch -> eventClass,
			"handler" => $handler -> handler,
			"handlerKey" => $handler -> handlerKey,
			"module" => $handler -> module,
			"observer" => $handler -> observer,
			"executionMode" => $handler -> executionMode,
			"status" => HandlerLog::STATUS_PENDING,
			"attemptNumber" => $attempt,
			"retryOf" => $handler -> id,
			"errorMessage" => null,
			"reportID" => null,
			"traceLocation" => null,
			"duration" => 0
		)) -> save();

		$this -> syncDispatch($dispatch);
		return $retry;
	}

	/**
	 * @return	HandlerLog[]
	 */
	public function pendingHandlers(int $dispatchID, ?string $executionMode = null): array {
		$query = HandlerLog::query()
			-> where(HandlerLog::col("dispatchID"), $dispatchID)
			-> where(HandlerLog::col("status"), HandlerLog::STATUS_PENDING)
			-> sort(HandlerLog::col("id"), "ASC");

		if (!empty($executionMode))
			$query -> where(HandlerLog::col("executionMode"), ExecutionMode::normalize($executionMode));

		return $query -> all();
	}

	/**
	 * Pick a stored dispatch back up, in whatever process reached it.
	 */
	public function resumeDispatch(BaseEvent $event, int $dispatchID, string $mode, int $totalHandlers): DispatchLog {
		$dispatch = DispatchLog::getByID($dispatchID, Model::MUST_EXIST);
		$dispatch -> eventClass = $event::class;
		$dispatch -> eventPayload = $event -> toArray();
		$dispatch -> dispatchMode = $mode;

		if ($dispatch -> totalHandlers <= 0)
			$dispatch -> totalHandlers = $totalHandlers;

		return $dispatch -> save();
	}

	/**
	 * Recount a dispatch's handlers and work out where it now stands.
	 */
	public function syncDispatch(DispatchLog|int $dispatch, ?float $duration = null): DispatchLog {
		if (is_int($dispatch))
			$dispatch = DispatchLog::getByID($dispatch, Model::MUST_EXIST);

		$deferred = $this -> isDeferred($dispatch);

		// A buffered dispatch has nothing in the database to count, so the
		// numbers come from the buffer rather than a query that would find
		// nothing.
		$handlers = $deferred
			? ($this -> bufferedHandlers[spl_object_id($dispatch)] ?? array())
			: HandlerLog::query() -> where(HandlerLog::col("dispatchID"), $dispatch -> id) -> all();

		$pending = 0;
		$completed = 0;
		$succeeded = 0;
		$errored = 0;

		foreach ($handlers as $handler) {
			if ($handler -> status === HandlerLog::STATUS_PENDING) {
				$pending++;
				continue;
			}

			$completed++;

			if ($handler -> status === HandlerLog::STATUS_SUCCESS)
				$succeeded++;
			else if ($handler -> status === HandlerLog::STATUS_FAILED)
				$errored++;
		}

		$dispatch -> totalHandlers = max($dispatch -> totalHandlers, count($handlers));
		$dispatch -> pendingHandlers = $pending;
		$dispatch -> completedHandlers = $completed;
		$dispatch -> successHandlers = $succeeded;
		$dispatch -> errorHandlers = $errored;

		if ($duration !== null)
			$dispatch -> duration = $duration;

		if ($pending > 0) {
			$dispatch -> status = ($completed > 0)
				? DispatchLog::STATUS_RUNNING
				: DispatchLog::STATUS_PENDING;
		} else if ($errored <= 0) {
			$dispatch -> status = DispatchLog::STATUS_SUCCESS;
		} else if ($succeeded <= 0) {
			$dispatch -> status = DispatchLog::STATUS_FAILED;
		} else {
			$dispatch -> status = DispatchLog::STATUS_PARTIAL;
		}

		if ($deferred) {
			$dispatch -> updated = time();
			return $dispatch;
		}

		return $dispatch -> save();
	}

	public function finishHandler(
		HandlerLog $handler,
		string $status,
		float $duration,
		?string $errorMessage = null,
		?string $reportID = null,
		?string $traceLocation = null
	): HandlerLog {
		$handler -> status = $status;
		$handler -> errorMessage = $errorMessage;
		$handler -> reportID = $reportID;
		$handler -> traceLocation = $traceLocation;
		$handler -> duration = $duration;

		if ($this -> isHandlerDeferred($handler)) {
			$handler -> updated = time();
			return $handler;
		}

		return $handler -> save();
	}

	/**
	 * Whether this dispatch is still only in memory.
	 */
	public function isDeferred(DispatchLog $dispatch): bool {
		return isset($this -> bufferedDispatches[spl_object_id($dispatch)]);
	}

	/**
	 * Write one buffered dispatch out now, with its handlers.
	 *
	 * Called when a dispatch has a handler running in another process: the
	 * runner reads these rows back, and cannot wait for the shutdown flush of
	 * a request it knows nothing about.
	 */
	public function persist(DispatchLog $dispatch): DispatchLog {
		$oid = spl_object_id($dispatch);

		if (!isset($this -> bufferedDispatches[$oid]))
			return $dispatch;

		$handlers = $this -> bufferedHandlers[$oid] ?? array();
		unset($this -> bufferedDispatches[$oid], $this -> bufferedHandlers[$oid]);

		$dispatch -> save();

		foreach ($handlers as $handler) {
			unset($this -> handlerDispatch[spl_object_id($handler)]);
			$handler -> dispatchID = $dispatch -> id;
			$handler -> save();
		}

		return $dispatch;
	}

	/**
	 * Write everything buffered, in a few batched inserts.
	 *
	 * Called from the shutdown handler with the response already sent. Safe to
	 * call again; it does nothing when the buffer is empty.
	 *
	 * @return	int		Dispatches written.
	 */
	public function flush(): int {
		if (empty($this -> bufferedDispatches))
			return 0;

		// Detached first, so anything recorded during the flush queues up
		// cleanly instead of being written twice or lost.
		$dispatches = $this -> bufferedDispatches;
		$handlers = $this -> bufferedHandlers;

		$this -> bufferedDispatches = array();
		$this -> bufferedHandlers = array();
		$this -> handlerDispatch = array();

		try {
			$this -> write($dispatches, $handlers);
		} catch (\Throwable $e) {
			// The trail is a record, not the work itself. A failed write must
			// never escape shutdown and take the request down with it.
			error_log("[blink][lightrail] could not flush the dispatch trail: {$e}");
		}

		return count($dispatches);
	}

	/**
	 * Write out only the dispatches whose handlers have all finished.
	 *
	 * One still waiting on a shutdown handler stays buffered: writing it now
	 * would record a dispatch whose rows are not settled yet.
	 */
	protected function flushResolved(): void {
		$dispatches = array();
		$handlers = array();

		foreach ($this -> bufferedDispatches as $oid => $dispatch) {
			$rows = $this -> bufferedHandlers[$oid] ?? array();

			foreach ($rows as $handler) {
				if ($handler -> status === HandlerLog::STATUS_PENDING)
					continue 2;
			}

			$dispatches[$oid] = $dispatch;
			$handlers[$oid] = $rows;
		}

		if (empty($dispatches))
			return;

		foreach ($dispatches as $oid => $dispatch) {
			unset($this -> bufferedDispatches[$oid], $this -> bufferedHandlers[$oid]);

			foreach ($handlers[$oid] as $handler)
				unset($this -> handlerDispatch[spl_object_id($handler)]);
		}

		try {
			$this -> write($dispatches, $handlers);
		} catch (\Throwable $e) {
			error_log("[blink][lightrail] could not flush the dispatch trail: {$e}");
		}
	}

	protected function isHandlerDeferred(HandlerLog $handler): bool {
		return isset($this -> handlerDispatch[spl_object_id($handler)]);
	}

	/**
	 * @param	array<int, DispatchLog>		$dispatches		Keyed by object id.
	 * @param	array<int, HandlerLog[]>	$handlers		Keyed by dispatch object id.
	 */
	protected function write(array $dispatches, array $handlers): void {
		$now = time();
		$records = array();

		foreach ($dispatches as $dispatch) {
			$dispatch -> updated = $now;
			$records[] = (array) $dispatch -> toRecord(primary: false);
		}

		DB::inserts(DispatchLog::$table, $records);

		// A batched insert cannot hand back the ids it generated, so they are
		// read back in one query keyed on the correlation ids assigned above.
		$correlations = array_map(
			fn (DispatchLog $dispatch) => $dispatch -> correlationID,
			array_values($dispatches)
		);

		$byCorrelation = array();

		foreach (DB::table(DispatchLog::$table) -> where("correlation_id", $correlations) -> all() as $row)
			$byCorrelation[$row -> correlation_id] = (int) $row -> id;

		$handlerRecords = array();

		foreach ($dispatches as $oid => $dispatch) {
			$dispatchID = $byCorrelation[$dispatch -> correlationID] ?? null;

			if ($dispatchID === null)
				continue;

			$dispatch -> id = $dispatchID;

			foreach (($handlers[$oid] ?? array()) as $handler) {
				$handler -> dispatchID = $dispatchID;
				$handler -> updated = $now;
				$handlerRecords[] = (array) $handler -> toRecord(primary: false);
			}
		}

		if (!empty($handlerRecords))
			DB::inserts(HandlerLog::$table, $handlerRecords);
	}
}
