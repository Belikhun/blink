<?php

namespace Blink\Lightrail;

use Blink\DB;
use Blink\Ether;
use Blink\Ether\Event\EventDispatchContext;
use Blink\Lightrail\Event\BaseEvent;
use Blink\Lightrail\Event\EventBatch;
use Blink\Lightrail\Event\GenericEvent;
use Blink\Lightrail\Model\BatchedEvent;
use Blink\Lightrail\Model\DispatchLog;
use Blink\Lightrail\Model\HandlerLog;
use Blink\Lightrail\Registry\HandlerDescriptor;
use Blink\Lightrail\Registry\ListenerRegistry;
use Blink\Lightrail\Service\Recorder;
use Blink\Lightrail\Task\BatchFlushTask;
use Blink\Lightrail\Task\DispatchTask;
use Blink\Model;
use Blink\Ticker;

/**
 * Decides where each handler of an emitted event runs, and gets it there.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EventCoordinator {
	const MODE_MIXED = "mixed";

	/**
	 * Dispatches waiting for shutdown, in this process.
	 *
	 * @var array<int, array{event: BaseEvent, dispatch: DispatchLog, handlers: HandlerLog[]}>
	 */
	protected static array $shutdownQueue = array();

	/**
	 * When this process last scheduled a flush per batch, so a burst touches
	 * the queue about once a window instead of once an event.
	 *
	 * @var array<string, int>
	 */
	protected static array $batchScheduledAt = array();

	/**
	 * Events appended per batch in this process, for the early flush.
	 *
	 * @var array<string, int>
	 */
	protected static array $batchAppended = array();

	/**
	 * The open placeholder dispatch per batch, so events arriving during one
	 * window share a single pending row.
	 *
	 * @var array<string, int>
	 */
	protected static array $batchDispatch = array();

	public function __construct(
		protected ListenerRegistry $registry,
		protected Dispatcher $dispatcher,
		protected Recorder $recorder
	) {}

	public function registerListener(
		string $eventClass,
		string|array $handler,
		int $priority = 0,
		array $context = array(),
		string $executionMode = ExecutionMode::IMMEDIATE,
		array $middleware = array(),
		array $excludedMiddleware = array()
	): static {
		// No rebuild: the registry keeps runtime listeners apart from the
		// discovered set and invalidates its own per-event memo.
		$this -> registry -> registerListener(
			$eventClass, $handler, null, $priority, $context, $executionMode, $middleware, $excludedMiddleware
		);

		return $this;
	}

	public function registerObserverClass(string $className): static {
		$this -> registry -> registerObserverClass($className);
		$this -> rebuildCache();

		return $this;
	}

	public function rebuildCache(): void {
		$this -> registry -> rebuild();
	}

	/**
	 * Emit an event to everything watching it.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public function emit(BaseEvent $event): array {
		$handlers = $this -> registry -> handlersForEvent($event);
		$mode = $this -> resolveDispatchMode($handlers);

		$context = new EventDispatchContext($event, $mode);

		$results = Ether::dispatchEvent($context, function (EventDispatchContext $context) use ($event, $handlers, $mode) {
			$context -> dispatched = true;
			return $this -> runDispatch($event, $handlers, $mode);
		});

		// A dispatch middleware that returned without calling next means no
		// handler ran, so there is nothing to report.
		return ($context -> dispatched && is_array($results))
			? $results
			: array();
	}

	public function dispatch(BaseEvent $event): array {
		return $this -> dispatcher -> dispatch($event);
	}

	/**
	 * Run the handlers that were waiting for the response to go out.
	 */
	public function flushShutdownDispatches(): void {
		if (empty(static::$shutdownQueue))
			return;

		$queue = static::$shutdownQueue;
		static::$shutdownQueue = array();

		foreach ($queue as $entry) {
			try {
				$dispatch = $entry["dispatch"];

				if ($dispatch -> status === DispatchLog::STATUS_PENDING) {
					$dispatch -> status = DispatchLog::STATUS_RUNNING;
					$dispatch -> save();
				}

				$this -> dispatcher -> dispatchHandlerLogs($entry["event"], $dispatch, $entry["handlers"]);
			} catch (\Throwable $e) {
				// Shutdown stays fail-safe: one dispatch failing must not stop
				// the ones behind it.
				error_log("[blink][lightrail] shutdown dispatch failed: {$e}");
			}
		}
	}

	/**
	 * Run a stored dispatch's pending handlers now, wherever we are.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public function dispatchPendingNow(int $dispatchID): array {
		$dispatch = DispatchLog::getByID($dispatchID, Model::MUST_EXIST);

		if (!in_array($dispatch -> status, array(
			DispatchLog::STATUS_PENDING,
			DispatchLog::STATUS_RUNNING
		), true)) {
			return array();
		}

		$event = $this -> eventFromDispatch($dispatch);

		if (!$event)
			return array();

		// Whatever was queued for this dispatch is about to be done here, so
		// the queued copy would only run it twice.
		Ticker::forget(DispatchTask::keyFor($dispatch -> id));

		return $this -> dispatcher -> dispatchPending($event, $dispatch -> id);
	}

	/**
	 * Give a failed handler another go, as a new attempt.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public function retryHandlerNow(int $handlerID): array {
		$handler = HandlerLog::getByID($handlerID, Model::MUST_EXIST);

		if ($handler -> status !== HandlerLog::STATUS_FAILED)
			return array();

		$dispatch = DispatchLog::getByID($handler -> dispatchID, Model::MUST_EXIST);
		$event = $this -> eventFromDispatch($dispatch);

		if (!$event)
			return array();

		$retry = $this -> recorder -> queueRetryHandler($dispatch, $handler);
		return $this -> dispatcher -> dispatchHandlerLogs($event, $dispatch, array( $retry ));
	}

	/**
	 * Sort the handlers by when they run and set each group going.
	 *
	 * @param	HandlerDescriptor[]		$handlers
	 * @return	array<int, array<string, mixed>>
	 */
	protected function runDispatch(BaseEvent $event, array $handlers, string $dispatchMode): array {
		$batched = array();
		$direct = array();

		foreach ($handlers as $handler) {
			if (ExecutionMode::normalize($handler -> executionMode) === ExecutionMode::BATCHED)
				$batched[] = $handler;
			else
				$direct[] = $handler;
		}

		if (!empty($batched))
			$this -> bufferBatchedHandlers($event, $batched);

		// An event whose only handlers are batched is already represented by
		// the batch's own dispatch row. A second row with no handlers in it
		// would say nothing.
		if (empty($direct))
			return array();

		$dispatch = $this -> recorder -> queueDispatch($event, $dispatchMode, $direct, defer: true);
		$logs = $this -> recorder -> queueHandlers($dispatch, $direct);

		$immediate = array();
		$shutdown = array();
		$background = array();

		foreach ($logs as $log) {
			switch (ExecutionMode::normalize($log -> executionMode)) {
				case ExecutionMode::SHUTDOWN:
					$shutdown[] = $log;
					break;

				case ExecutionMode::BACKGROUND:
					$background[] = $log;
					break;

				default:
					$immediate[] = $log;
					break;
			}
		}

		$results = $this -> dispatcher -> dispatchHandlerLogs($event, $dispatch, $immediate);

		if (!empty($shutdown)) {
			// Written now rather than at the shutdown flush, so the history
			// shows the dispatch waiting instead of it appearing only once it
			// has already run.
			$this -> recorder -> persist($dispatch);

			static::$shutdownQueue[] = array(
				"event" => $event,
				"dispatch" => $dispatch,
				"handlers" => $shutdown
			);
		}

		if (!empty($background)) {
			// A runner in another process reads these rows, so they have to be
			// on disk before the task that points at them is queued.
			$this -> recorder -> persist($dispatch);
			Ticker::queue(new DispatchTask($dispatch -> id, ExecutionMode::BACKGROUND));
		}

		return $results;
	}

	/**
	 * Put this event in each batched handler's buffer and make sure a flush is
	 * on its way.
	 *
	 * @param	HandlerDescriptor[]		$handlers
	 */
	protected function bufferBatchedHandlers(BaseEvent $event, array $handlers): void {
		$payload = $event -> toArray();
		$now = time();

		foreach ($handlers as $descriptor) {
			$window = (int) ($descriptor -> batch["window"] ?? 60);
			$maxSize = (int) ($descriptor -> batch["maxSize"] ?? 0);
			$keyPath = $descriptor -> batch["key"] ?? null;
			$batchKey = "";

			if (!empty($keyPath)) {
				$value = EventBatch::readPath($payload, $keyPath);

				if ($value !== null) {
					$batchKey = is_scalar($value)
						? (string) $value
						: (string) json_encode($value);
				}
			}

			$batchKey = mb_substr($batchKey, 0, 191);
			$id = "{$descriptor -> key}|{$batchKey}";

			$dispatchID = static::$batchDispatch[$id]
				?? $this -> resolveBatchPlaceholder($descriptor, $batchKey);

			static::$batchDispatch[$id] = $dispatchID;

			BatchedEvent::create(array(
				"handlerKey" => $descriptor -> key,
				"batchKey" => $batchKey,
				"eventClass" => $event::class,
				"eventPayload" => $payload,
				"claimToken" => "",
				"dispatchID" => $dispatchID,
				"created" => $now,
				"updated" => $now
			)) -> save();

			static::$batchAppended[$id] = (static::$batchAppended[$id] ?? 0) + 1;

			// Checked at most once a window per batch: enough to coalesce a
			// burst, while a long-running process still schedules again once
			// its previous flush has drained.
			if (($now - (static::$batchScheduledAt[$id] ?? 0)) >= max($window, 1)) {
				BatchFlushTask::schedule($descriptor -> key, $batchKey, $window);
				static::$batchScheduledAt[$id] = $now;
			}

			if ($maxSize > 0 && static::$batchAppended[$id] >= $maxSize) {
				BatchFlushTask::flushNow($descriptor -> key, $batchKey);
				static::$batchAppended[$id] = 0;
			}
		}
	}

	/**
	 * The placeholder dispatch for a batch still filling up: the one another
	 * process already opened, or a fresh one.
	 */
	protected function resolveBatchPlaceholder(HandlerDescriptor $descriptor, string $batchKey): int {
		$row = DB::table(BatchedEvent::$table)
			-> where("handler_key", $descriptor -> key)
			-> where("batch_key", $batchKey)
			-> where("claim_token", "")
			-> where("dispatch_id", ">", 0)
			-> first();

		if ($row && (int) $row -> dispatch_id > 0)
			return (int) $row -> dispatch_id;

		return $this -> dispatcher -> createBatchPlaceholder($descriptor, $batchKey) -> id;
	}

	/**
	 * One word for where this event's handlers run, for the dispatch row.
	 *
	 * @param	HandlerDescriptor[]		$handlers
	 */
	protected function resolveDispatchMode(array $handlers): string {
		if (empty($handlers))
			return ExecutionMode::IMMEDIATE;

		$modes = array_values(array_unique(array_map(
			fn (HandlerDescriptor $handler) => $handler -> executionMode,
			$handlers
		)));

		return (count($modes) === 1)
			? (string) $modes[0]
			: static::MODE_MIXED;
	}

	/**
	 * Rebuild the event a stored dispatch was opened for.
	 */
	protected function eventFromDispatch(DispatchLog $dispatch): ?BaseEvent {
		$eventClass = (string) ($dispatch -> eventClass ?? "");
		$payload = is_array($dispatch -> eventPayload)
			? $dispatch -> eventPayload
			: array();

		// The class can have been renamed or removed since the row was
		// written, and a dispatch nobody can reconstruct is simply skipped.
		if (empty($eventClass) || !class_exists($eventClass) || !is_subclass_of($eventClass, BaseEvent::class))
			return null;

		return $eventClass::fromArray($payload);
	}
}
