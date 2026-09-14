<?php

namespace Blink\Lightrail\Task;

use Blink\DB;
use Blink\Essence;
use Blink\Lightrail\Dispatcher;
use Blink\Lightrail\Event\BaseEvent;
use Blink\Lightrail\Event\EventBatch;
use Blink\Lightrail\Model\BatchedEvent;
use Blink\Lightrail\Model\DispatchLog;
use Blink\Lightrail\Model\HandlerLog;
use Blink\Lightrail\Registry\ListenerRegistry;
use Blink\Model;
use Blink\Ticker;
use Blink\Ticker\Task;

/**
 * Drain one batched handler's buffer and call it once with everything in it.
 *
 * Debounced and coalesced: only one flush per handler and batch key is ever
 * waiting, so a burst of a hundred events collapses into a single call.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class BatchFlushTask extends Task {
	public string $handlerKey;

	public string $batchKey;

	public function __construct(string $handlerKey, string $batchKey = "") {
		$this -> handlerKey = $handlerKey;
		$this -> batchKey = $batchKey;
		$this -> queue = \CONFIG::$LIGHTRAIL_QUEUE;
	}

	/**
	 * Ask for a flush after the debounce window, unless one is already waiting.
	 *
	 * A second call during the window changes nothing: the flush already
	 * queued will pick up whatever has arrived by the time it runs.
	 */
	public static function schedule(string $handlerKey, string $batchKey, int $window): void {
		Ticker::queueUnique(new static($handlerKey, $batchKey), max($window, 0));
	}

	/**
	 * Pull a waiting flush forward to now, for a batch that filled up before
	 * its window closed. Queues one if none is waiting.
	 */
	public static function flushNow(string $handlerKey, string $batchKey): void {
		$task = new static($handlerKey, $batchKey);

		// Already waiting: move it rather than adding a second one.
		if (Ticker::pullForward($task -> key()))
			return;

		Ticker::queueUnique($task);
	}

	public function key(): ?string {
		return "lightrail:batch:{$this -> handlerKey}|{$this -> batchKey}";
	}

	public function label(): string {
		return "lightrail batch {$this -> handlerKey}"
			. ($this -> batchKey !== "" ? " [{$this -> batchKey}]" : "");
	}

	public function run(): void {
		if (empty($this -> handlerKey))
			return;

		$descriptor = Essence::make(ListenerRegistry::class) -> descriptorByKey($this -> handlerKey);

		// The handler was renamed or removed while events were waiting for it.
		// Nothing will ever consume them, so they go rather than accumulate.
		if ($descriptor === null) {
			DB::table(BatchedEvent::$table)
				-> where("handler_key", $this -> handlerKey)
				-> where("batch_key", $this -> batchKey)
				-> delete();

			return;
		}

		$token = bin2hex(random_bytes(16));

		// Claim a snapshot. A row arriving after this, including while the
		// handler runs, keeps an empty token and waits for the next flush,
		// so nothing emitted mid-call is lost.
		DB::table(BatchedEvent::$table)
			-> where("handler_key", $this -> handlerKey)
			-> where("batch_key", $this -> batchKey)
			-> where("claim_token", "")
			-> set("claim_token", $token)
			-> set("updated", time())
			-> update();

		$rows = BatchedEvent::query()
			-> where(BatchedEvent::col("claimToken"), $token)
			-> sort(BatchedEvent::col("id"), "ASC")
			-> all();

		if (empty($rows))
			return;

		$events = $this -> applyDedupe(
			$this -> reconstructEvents($rows),
			$descriptor -> batch["dedupe"] ?? null
		);

		$batch = new EventBatch($events, $this -> handlerKey, (string) $descriptor -> eventClass, $this -> batchKey);
		$dispatcher = Essence::make(Dispatcher::class);

		$candidates = array();
		foreach ($rows as $row)
			$candidates[(int) $row -> dispatchID] = true;

		$dispatch = $dispatcher -> resolveBatchDispatch(array_keys($candidates), $descriptor, $this -> batchKey);

		DB::table(BatchedEvent::$table)
			-> where("claim_token", $token)
			-> set("dispatch_id", $dispatch -> id)
			-> set("updated", time())
			-> update();

		try {
			$results = $dispatcher -> runBatch($dispatch, $batch, $descriptor);
		} catch (\Throwable $e) {
			$this -> release($token);
			throw $e;
		}

		foreach ($results as $result) {
			if (empty($result["errored"]))
				continue;

			// The claimed rows go back in the buffer and the task throws, so
			// Ticker retries the whole batch with its usual holdoff. The
			// placeholder has already been marked failed; the retry opens a
			// fresh one.
			$this -> release($token);
			throw new \RuntimeException("Batched handler {$this -> handlerKey} failed, the batch will be tried again.");
		}

		DB::table(BatchedEvent::$table)
			-> where("claim_token", $token)
			-> delete();

		// Two processes can each have opened a placeholder for the same batch.
		// The rows all point at one of them now, so the other owns nothing.
		foreach (array_keys($candidates) as $candidate)
			$this -> foldDuplicatePlaceholder((int) $candidate, (int) $dispatch -> id);
	}

	/**
	 * Delete a placeholder that lost the race and now owns no buffered rows.
	 * One that already finished, failed included, is history and stays.
	 */
	protected function foldDuplicatePlaceholder(int $dispatchID, int $primaryID): void {
		if ($dispatchID <= 0 || $dispatchID === $primaryID)
			return;

		$dispatch = DispatchLog::getByID($dispatchID, Model::IGNORE_MISSING);

		if (!$dispatch || !in_array($dispatch -> status, array(
			DispatchLog::STATUS_PENDING,
			DispatchLog::STATUS_RUNNING
		), true)) {
			return;
		}

		DB::table(HandlerLog::$table) -> where("dispatch_id", $dispatchID) -> delete();
		DB::table(DispatchLog::$table) -> where("id", $dispatchID) -> delete();
	}

	/**
	 * @param	BatchedEvent[]	$rows
	 * @return	BaseEvent[]
	 */
	protected function reconstructEvents(array $rows): array {
		$events = array();

		foreach ($rows as $row) {
			$class = $row -> eventClass;

			if (!class_exists($class) || !is_subclass_of($class, BaseEvent::class))
				continue;

			$events[] = $class::fromArray($row -> eventPayload);
		}

		return $events;
	}

	/**
	 * Keep only the latest event per value at a dot path. Events with nothing
	 * at that path are all kept, since there is no basis to call them
	 * duplicates.
	 *
	 * @param	BaseEvent[]		$events
	 * @return	BaseEvent[]
	 */
	protected function applyDedupe(array $events, ?string $path): array {
		if (empty($path))
			return $events;

		$kept = array();
		$byKey = array();

		foreach ($events as $event) {
			$value = EventBatch::readPath($event -> toArray(), $path);

			if ($value === null) {
				$kept[] = $event;
				continue;
			}

			$key = is_scalar($value)
				? (string) $value
				: (string) json_encode($value);

			$byKey[$key] = $event;
		}

		return array_merge($kept, array_values($byKey));
	}

	protected function release(string $token): void {
		DB::table(BatchedEvent::$table)
			-> where("claim_token", $token)
			-> set("claim_token", "")
			-> set("updated", time())
			-> update();
	}
}
