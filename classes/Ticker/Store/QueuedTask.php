<?php

namespace Blink\Ticker\Store;

use Blink\Ticker\Exception\TaskUnserializable;
use Blink\Ticker\Task;

/**
 * One row of a queue, with the task still asleep inside it.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class QueuedTask {
	public int $id;

	public string $queue;

	/**
	 * The serialized task. Left asleep until something asks for it, so
	 * listing a queue never has to be able to construct what is in it.
	 */
	public string $payload;

	public ?string $key;

	/**
	 * How many times this has been claimed, the current claim included.
	 */
	public int $attempts;

	/**
	 * When the task became eligible to run.
	 */
	public int $available;

	/**
	 * When the current claim was taken, or zero when nobody holds it.
	 */
	public int $reserved;

	/**
	 * When the current claim lapses and another runner may take over.
	 */
	public int $expires;

	/**
	 * Proof of the current claim. A release or delete carrying the wrong one
	 * changes nothing, which is what stops a runner that overran its claim
	 * from deleting work another runner has since taken.
	 */
	public ?string $token;

	public int $created;

	protected ?Task $task = null;

	public static function fromRow(object $row): static {
		$queued = new static();

		$queued -> id = (int) $row -> id;
		$queued -> queue = (string) $row -> queue;
		$queued -> payload = (string) $row -> payload;
		$queued -> key = !empty($row -> task_key) ? (string) $row -> task_key : null;
		$queued -> attempts = (int) $row -> attempts;
		$queued -> available = (int) $row -> available;
		$queued -> reserved = (int) $row -> reserved;
		$queued -> expires = (int) $row -> expires;
		$queued -> token = !empty($row -> token) ? (string) $row -> token : null;
		$queued -> created = (int) $row -> created;

		return $queued;
	}

	/**
	 * Wake the task up.
	 *
	 * @throws	TaskUnserializable	when the payload no longer describes a task.
	 */
	public function task(): Task {
		if ($this -> task !== null)
			return $this -> task;

		// A rename or a deleted class leaves rows describing something that no
		// longer exists. PHP hands back `__PHP_Incomplete_Class` rather than
		// failing, so the check has to be explicit.
		$task = @unserialize($this -> payload);

		if (!is_object($task) || !($task instanceof Task))
			throw new TaskUnserializable($this -> id, static::describe($task));

		return $this -> task = $task;
	}

	/**
	 * A name for the task without waking it, for a listing that must not fail
	 * on a row it cannot construct.
	 */
	public function label(): string {
		$matches = array();

		// The class name sits at the head of the serialized payload, which is
		// the only part safe to read for a class that may not exist.
		if (preg_match('/^O:\d+:"([^"]+)"/', $this -> payload, $matches))
			return $matches[1];

		return "[unknown]";
	}

	protected static function describe(mixed $task): string {
		if (is_object($task))
			return get_class($task);

		return gettype($task);
	}
}
