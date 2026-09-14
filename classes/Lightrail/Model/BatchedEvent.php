<?php

namespace Blink\Lightrail\Model;

use Blink\Model;

/**
 * One event waiting in a batched handler's buffer.
 *
 * Rows pile up as events are emitted, and a flush claims a snapshot of them
 * with a token, calls the handler once with the lot, then deletes exactly what
 * it claimed. Anything emitted during the call is left for the next flush
 * rather than silently thrown away.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * @extends		Model<BatchedEvent>
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class BatchedEvent extends Model {
	public static string $table = "lightrail_batched_events";

	public static $fillables = array(
		"id",
		"handlerKey" => "handler_key",
		"batchKey" => "batch_key",
		"eventClass" => "event_class",
		"eventPayload" => "event_payload",
		"claimToken" => "claim_token",
		"dispatchID" => "dispatch_id",
		"created",
		"updated"
	);

	public int $id;

	public string $handlerKey;

	/**
	 * Splits one handler's buffer into independent batches, when the handler
	 * asked for that. Empty means one batch for everything.
	 */
	public string $batchKey = "";

	public string $eventClass;

	public array $eventPayload = array();

	/**
	 * Set by the flush that claimed this row. Empty while it is still waiting.
	 */
	public string $claimToken = "";

	public int $dispatchID = 0;

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
