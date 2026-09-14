<?php

namespace Blink\Lightrail\Event;

/**
 * The events handed to a batched handler all at once.
 *
 * It is itself an event so it can travel through the dispatcher and the trail
 * like any other, but its own context carries only a summary: the real events
 * live in {@see EventBatch::$events} for the handler to read.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EventBatch extends BaseEvent {
	/** @var BaseEvent[] */
	public array $events;

	public string $handlerKey;

	public string $sourceEventClass;

	public string $batchKey;

	/**
	 * @param	BaseEvent[]		$events
	 */
	public function __construct(
		array $events = array(),
		string $handlerKey = "",
		string $sourceEventClass = "",
		string $batchKey = "",
		?int $created = null
	) {
		$this -> events = array_values($events);
		$this -> handlerKey = $handlerKey;
		$this -> sourceEventClass = $sourceEventClass;
		$this -> batchKey = $batchKey;

		parent::__construct(array(
			"handlerKey" => $handlerKey,
			"sourceEventClass" => $sourceEventClass,
			"batchKey" => $batchKey,
			"count" => count($this -> events)
		), $created);
	}

	public function count(): int {
		return count($this -> events);
	}

	public function isEmpty(): bool {
		return empty($this -> events);
	}

	/**
	 * @return	BaseEvent[]
	 */
	public function events(): array {
		return $this -> events;
	}

	/**
	 * Collect one value out of every event in the batch.
	 *
	 * The usual reason a handler is batched at all: it wants the set of things
	 * that changed, not each change one at a time.
	 *
	 * ```php
	 * $ids = $batch -> pluck("context.instance");
	 * ```
	 *
	 * @param	string	$path		Dot path into the event's array form.
	 * @param	bool	$unique		Collapse repeats.
	 * @return	mixed[]
	 */
	public function pluck(string $path, bool $unique = true): array {
		$values = array();

		foreach ($this -> events as $event) {
			$value = static::readPath($event -> toArray(), $path);

			if ($value !== null)
				$values[] = $value;
		}

		return $unique
			? array_values(array_unique($values, SORT_REGULAR))
			: $values;
	}

	/**
	 * Read a dot path out of an event's array form, or null if any part of it
	 * is missing.
	 */
	public static function readPath(array $data, string $path): mixed {
		$cursor = $data;

		foreach (explode(".", $path) as $segment) {
			if (!is_array($cursor) || !array_key_exists($segment, $cursor))
				return null;

			$cursor = $cursor[$segment];
		}

		return $cursor;
	}
}
