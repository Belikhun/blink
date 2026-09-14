<?php

namespace Blink\Lightrail;

use Blink\ErrorPage\Instance;
use Blink\Essence;
use Blink\Ether;
use Blink\Ether\Handler\HandlerInvocationContext;
use Blink\Lightrail\Event\BaseEvent;
use Blink\Lightrail\Event\EventBatch;
use Blink\Lightrail\Model\DispatchLog;
use Blink\Lightrail\Model\HandlerLog;
use Blink\Lightrail\Registry\HandlerDescriptor;
use Blink\Lightrail\Registry\ListenerRegistry;
use Blink\Lightrail\Service\Recorder;
use Blink\Model;
use function Blink\getRelativePath;

/**
 * Runs handlers and records what happened.
 *
 * Fail-safe on purpose: one handler raising must not stop the rest, and must
 * not reach the code that emitted the event. A handler that fails leaves a
 * failed row and an error report, and the next one runs.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Dispatcher {
	public function __construct(
		protected ListenerRegistry $registry,
		protected Recorder $recorder
	) {}

	/**
	 * Record an emission and run whatever is meant to run now.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public function dispatch(BaseEvent $event, string $mode = ExecutionMode::IMMEDIATE, ?int $dispatchID = null): array {
		$started = microtime(true);
		$handlers = $this -> registry -> handlersForEvent($event);

		if (empty($dispatchID)) {
			$dispatch = $this -> recorder -> queueDispatch($event, $mode, $handlers);
			$this -> recorder -> queueHandlers($dispatch, $handlers);
		} else {
			$dispatch = $this -> recorder -> resumeDispatch($event, $dispatchID, $mode, count($handlers));
			$this -> recorder -> ensureQueuedHandlers($dispatch, $handlers);
		}

		$elapsed = round((microtime(true) - $started) * 1000, 4);
		return $this -> dispatchPending($event, $dispatch, $elapsed);
	}

	/**
	 * Run the handlers of a dispatch that are still pending.
	 *
	 * @param	?string		$executionMode	Only handlers registered for this mode.
	 * @return	array<int, array<string, mixed>>
	 */
	public function dispatchPending(
		BaseEvent $event,
		DispatchLog|int $dispatch,
		float $startDuration = 0,
		?string $executionMode = null
	): array {
		if (is_int($dispatch))
			$dispatch = DispatchLog::getByID($dispatch, Model::MUST_EXIST);

		// A runner picking up a stored dispatch marks it running, so the
		// history shows progress rather than sitting at pending until it is
		// all over.
		if ($executionMode !== null && $dispatch -> status === DispatchLog::STATUS_PENDING) {
			$dispatch -> status = DispatchLog::STATUS_RUNNING;
			$dispatch -> save();
		}

		$handlers = $this -> recorder -> pendingHandlers($dispatch -> id, $executionMode);
		return $this -> dispatchHandlerLogs($event, $dispatch, $handlers, $startDuration);
	}

	/**
	 * Open a dispatch row for a batch that has not been flushed yet.
	 *
	 * Recorded as a real dispatch so a waiting batch shows up in the history
	 * with an id of its own, and moves from pending to running to done as the
	 * flush gets to it.
	 */
	public function createBatchPlaceholder(HandlerDescriptor $descriptor, string $batchKey = "", int $count = 0): DispatchLog {
		$event = new EventBatch(array(), $descriptor -> key, $descriptor -> eventClass, $batchKey);
		$event -> context["count"] = max(0, $count);

		$dispatch = $this -> recorder -> queueDispatch($event, ExecutionMode::BATCHED, array( $descriptor ));
		$this -> recorder -> queueHandlers($dispatch, array( $descriptor ));

		return $dispatch;
	}

	/**
	 * Pick which placeholder a flush should report against.
	 *
	 * The buffered rows each point at the dispatch that was open when they
	 * arrived, and the first of those still open is the one to use. When none
	 * is (every candidate is a past failed attempt) a fresh one is opened.
	 *
	 * @param	int[]	$candidateIDs
	 */
	public function resolveBatchDispatch(array $candidateIDs, HandlerDescriptor $descriptor, string $batchKey = ""): DispatchLog {
		sort($candidateIDs);

		foreach ($candidateIDs as $id) {
			if ((int) $id <= 0)
				continue;

			$dispatch = DispatchLog::getByID((int) $id, Model::IGNORE_MISSING);

			if ($dispatch && in_array($dispatch -> status, array(
				DispatchLog::STATUS_PENDING,
				DispatchLog::STATUS_RUNNING
			), true)) {
				return $dispatch;
			}
		}

		return $this -> createBatchPlaceholder($descriptor, $batchKey);
	}

	/**
	 * Call a batched handler once with everything buffered for it.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public function runBatch(DispatchLog $dispatch, EventBatch $batch, HandlerDescriptor $descriptor): array {
		$payload = $dispatch -> eventPayload;
		$payload["context"]["count"] = $batch -> count();

		$dispatch -> eventPayload = $payload;
		$dispatch -> status = DispatchLog::STATUS_RUNNING;
		$dispatch -> save();

		$handlers = $this -> recorder -> pendingHandlers($dispatch -> id);

		if (empty($handlers))
			$handlers = $this -> recorder -> queueHandlers($dispatch, array( $descriptor ));

		// The batch itself travels as the event, so handler middleware sees it
		// the same way it sees anything else.
		return $this -> dispatchHandlerLogs($batch, $dispatch, $handlers);
	}

	/**
	 * Invoke each pending handler row and record the outcome.
	 *
	 * @param	HandlerLog[]	$handlers
	 * @return	array<int, array<string, mixed>>
	 */
	public function dispatchHandlerLogs(
		BaseEvent $event,
		DispatchLog|int $dispatch,
		array $handlers,
		float $startDuration = 0
	): array {
		$started = microtime(true);

		if (is_int($dispatch))
			$dispatch = DispatchLog::getByID($dispatch, Model::MUST_EXIST);

		$results = array();

		foreach ($handlers as $handler)
			$results[] = $this -> invoke($event, $dispatch, $handler);

		$duration = $startDuration + round((microtime(true) - $started) * 1000, 4);
		$this -> recorder -> syncDispatch($dispatch, $duration);

		return $results;
	}

	/**
	 * @return	array<string, mixed>
	 */
	protected function invoke(BaseEvent $event, DispatchLog $dispatch, HandlerLog $handler): array {
		$started = microtime(true);
		$errored = false;
		$skipped = false;
		$error = null;
		$reportID = null;
		$location = null;

		try {
			$descriptor = $this -> registry -> descriptorByKey($handler -> handlerKey);
			$context = new HandlerInvocationContext($event, $handler);

			Ether::dispatchHandler(
				$context,
				static function (HandlerInvocationContext $context) use ($event, $handler) {
					$context -> invoked = true;
					return Essence::call($handler -> handler, array( $event ));
				},
				$descriptor ? $descriptor -> middleware : array(),
				$descriptor ? $descriptor -> excludedMiddleware : array()
			);

			// A middleware that returned without calling next leaves the
			// handler body unrun, which is a skip rather than a success.
			$skipped = !$context -> invoked;
		} catch (\Throwable $e) {
			$errored = true;
			$error = get_class($e) . ": " . $e -> getMessage();
			$location = getRelativePath($e -> getFile()) . ":" . $e -> getLine();

			try {
				$reportID = Instance::fromException($e) -> id;
			} catch (\Throwable $reportError) {
				// The stack trace is lost, but the failure is still recorded.
				// Losing the report must not turn into losing the dispatch.
				error_log("[blink][lightrail] could not file a report: {$reportError}");
			}
		}

		$duration = round((microtime(true) - $started) * 1000, 4);

		$status = $errored
			? HandlerLog::STATUS_FAILED
			: ($skipped ? HandlerLog::STATUS_SKIPPED : HandlerLog::STATUS_SUCCESS);

		$this -> recorder -> finishHandler($handler, $status, $duration, $error, $reportID, $location);

		return array(
			"correlationID" => $dispatch -> correlationID,
			"eventClass" => $event::class,
			"handler" => $handler -> handler,
			"handlerKey" => $handler -> handlerKey,
			"module" => $handler -> module,
			"observer" => $handler -> observer,
			"status" => $status,
			"errored" => $errored,
			"error" => $error,
			"reportID" => $reportID,
			"traceLocation" => $location,
			"duration" => $duration
		);
	}
}
