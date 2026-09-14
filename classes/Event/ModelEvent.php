<?php

namespace Blink\Event;

use Blink\Lightrail\Event\BaseEvent;
use Blink\Model;

/**
 * Something happened to a model row.
 *
 * The event carries the model's class and the row's id, never the instance:
 * a background handler reads it back in another process, where the object
 * would be stale anyway.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class ModelEvent extends BaseEvent {
	/**
	 * Models whose own writes must not emit events.
	 *
	 * Lightrail records every dispatch in models of its own. An event for one
	 * of those would record itself, emit again, and never stop.
	 */
	const SILENT = array(
		"Blink\\Lightrail\\Model\\",
		"Blink\\Trace\\"
	);

	/**
	 * @param	?int	$instanceID		The row's id, for a deletion where the
	 * 									model no longer has one.
	 */
	public static function fromModel(Model $model, ?int $instanceID = null): ?static {
		$modelClass = ltrim($model::class, "\\");

		foreach (static::SILENT as $prefix) {
			if (str_starts_with($modelClass, $prefix))
				return null;
		}

		return new static(array(
			"model" => $modelClass,
			"instance" => $instanceID ?? $model -> getPrimaryValue()
		));
	}

	/**
	 * The model class this event is about.
	 */
	public function modelClass(): string {
		return ltrim((string) ($this -> context["model"] ?? ""), "\\");
	}

	/**
	 * The row's id.
	 */
	public function instanceID(): ?int {
		$id = $this -> context["instance"] ?? null;

		return ($id === null) ? null : (int) $id;
	}

	/**
	 * What changed, as `[field => ["old" => ..., "new" => ...]]`.
	 *
	 * @return	array<string, array{old: mixed, new: mixed}>
	 */
	public function changes(): array {
		$changes = $this -> context["changes"] ?? array();

		return is_array($changes) ? $changes : array();
	}

	/**
	 * Whether any of the named fields changed. What an observer asks when it
	 * only cares about part of a row.
	 */
	public function changed(string ...$fields): bool {
		$changes = $this -> changes();

		foreach ($fields as $field) {
			if (array_key_exists($field, $changes))
				return true;
		}

		return false;
	}
}
