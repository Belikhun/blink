<?php

namespace Blink\Lightrail\Model;

use Blink\Model;

/**
 * One emission of one event, and how its handlers fared.
 *
 * Also what a deferred dispatch resumes from: a background handler reads the
 * event back out of here, in another process, long after the request that
 * emitted it has gone.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * @extends		Model<DispatchLog>
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DispatchLog extends Model {
	public const STATUS_PENDING = "pending";

	/**
	 * Handlers are running now, in a runner or at shutdown.
	 */
	public const STATUS_RUNNING = "running";

	public const STATUS_FAILED = "failed";

	public const STATUS_SUCCESS = "success";

	/**
	 * Some handlers succeeded and some did not.
	 */
	public const STATUS_PARTIAL = "partial";

	public static string $table = "lightrail_dispatch_logs";

	public static $fillables = array(
		"id",
		"correlationID" => "correlation_id",
		"eventClass" => "event_class",
		"eventPayload" => "event_payload",
		"dispatchMode" => "dispatch_mode",
		"status",
		"totalHandlers" => "total_handlers",
		"pendingHandlers" => "pending_handlers",
		"completedHandlers" => "completed_handlers",
		"successHandlers" => "success_handlers",
		"errorHandlers" => "error_handlers",
		"duration",
		"created",
		"updated"
	);

	public int $id;

	/**
	 * Ties this dispatch to the handler rows belonging to it.
	 */
	public string $correlationID;

	public string $eventClass;

	public array $eventPayload = array();

	public string $dispatchMode;

	public string $status;

	public int $totalHandlers = 0;

	public int $pendingHandlers = 0;

	public int $completedHandlers = 0;

	public int $successHandlers = 0;

	public int $errorHandlers = 0;

	public float $duration = 0;

	public int $created;

	public int $updated;

	protected static function processField(string $name, $value) {
		return match ($name) {
			"eventPayload" => (!empty($value) ? unserialize($value) : array()),
			default => $value
		};
	}

	protected function saveField(string $name) {
		return match ($name) {
			"eventPayload" => serialize($this -> eventPayload),
			default => parent::saveField($name)
		};
	}
}
