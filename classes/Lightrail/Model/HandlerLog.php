<?php

namespace Blink\Lightrail\Model;

use Blink\Lightrail\ExecutionMode;
use Blink\Model;

/**
 * One handler's turn at one event.
 *
 * A pending row is also the instruction to run it: a background dispatch
 * queues the handler key and the runner finds its work here.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * @extends		Model<HandlerLog>
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class HandlerLog extends Model {
	public const STATUS_PENDING = "pending";

	public const STATUS_SUCCESS = "success";

	public const STATUS_FAILED = "failed";

	/**
	 * A middleware returned without calling next, so the handler body never
	 * ran. Not a failure: something decided it should not happen.
	 */
	public const STATUS_SKIPPED = "skipped";

	public static string $table = "lightrail_handler_logs";

	public static $fillables = array(
		"id",
		"dispatchID" => "dispatch_id",
		"correlationID" => "correlation_id",
		"eventClass" => "event_class",
		"handler",
		"handlerKey" => "handler_key",
		"module",
		"observer",
		"executionMode" => "execution_mode",
		"status",
		"attemptNumber" => "attempt_number",
		"retryOf" => "retry_of",
		"errorMessage" => "error_message",
		"reportID" => "report_id",
		"traceLocation" => "trace_location",
		"duration",
		"created",
		"updated"
	);

	public int $id;

	public int $dispatchID;

	public string $correlationID;

	public string $eventClass;

	public string $handler;

	public string $handlerKey;

	public ?string $module = null;

	public bool $observer = false;

	public string $executionMode = ExecutionMode::IMMEDIATE;

	public string $status;

	public int $attemptNumber = 1;

	/**
	 * The handler row this one is a retry of, when an operator asked for one.
	 */
	public ?int $retryOf = null;

	public ?string $errorMessage = null;

	/**
	 * The error page report holding the stack trace, when one could be filed.
	 */
	public ?string $reportID = null;

	public ?string $traceLocation = null;

	public float $duration = 0;

	public int $created;

	public int $updated;

	protected static function processField(string $name, $value) {
		return match ($name) {
			"observer" => (bool) $value,
			"attemptNumber" => (int) $value,
			"retryOf" => (!empty($value) ? (int) $value : null),
			"executionMode" => ExecutionMode::normalize((string) $value),
			default => $value
		};
	}

	protected function saveField(string $name) {
		return match ($name) {
			"observer" => $this -> observer ? 1 : 0,
			default => parent::saveField($name)
		};
	}
}
