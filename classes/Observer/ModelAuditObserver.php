<?php

namespace Blink\Observer;

use Blink\Event\ModelCreated;
use Blink\Event\ModelDeleted;
use Blink\Event\ModelEvent;
use Blink\Event\ModelUpdated;
use Blink\Lightrail\Attribute\Observe;
use Blink\Trace;
use Blink\Trace\Entry;
use Blink\Trace\Mode;
use Blink\Trace\Writer;

/**
 * Record every model save and deletion in the audit log.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ModelAuditObserver {
	/**
	 * Longest a changed value is kept at in the payload. A change can carry a
	 * whole text column, and the payload sits inline on the row.
	 */
	protected const CHANGE_VALUE_LIMIT = 255;

	/**
	 * Fields left out of the change list. `updated` moves on every save, so
	 * carrying it would report a change on every entry without telling the
	 * reader anything.
	 */
	protected const IGNORED_CHANGE_FIELDS = array( "updated" );

	/**
	 * Models whose writes are bookkeeping rather than something anyone did.
	 * The audit log's own rows, and the dispatch trail that this observer's
	 * very invocation produces.
	 */
	protected const SILENT_PREFIXES = array(
		"Blink\\Lightrail\\",
		"Blink\\Trace\\"
	);

	/**
	 * Models kept out of the generic trail, keyed by class name. Modules add
	 * theirs from `attach()` through {@see ignore()}.
	 *
	 * A model belongs here when its rows are storage detail of another entity
	 * or are rebuilt wholesale by a sync, so a "created" line per row records
	 * nothing a reader can act on.
	 *
	 * @var array<string, true>
	 */
	protected static array $ignored = array();

	/**
	 * Keep models out of the generic create, update and delete trail. Class
	 * names are matched exactly, with no inheritance.
	 */
	public static function ignore(string ...$classes): void {
		foreach ($classes as $class)
			static::$ignored[ltrim($class, "\\")] = true;
	}

	public static function isIgnored(string $class): bool {
		$class = ltrim($class, "\\");

		if (isset(static::$ignored[$class]) || $class === Entry::class)
			return true;

		foreach (static::SILENT_PREFIXES as $prefix) {
			if (str_starts_with($class, $prefix))
				return true;
		}

		return false;
	}

	#[Observe(ModelCreated::class)]
	public static function onModelCreated(ModelCreated $event, Writer $writer): void {
		static::record($event, $writer, Mode::CREATE, "trace.model.created");
	}

	#[Observe(ModelUpdated::class)]
	public static function onModelUpdated(ModelUpdated $event, Writer $writer): void {
		$payload = static::changePayload($event);

		// A save that changed nothing is not an update anyone needs to read.
		if (empty($payload["changeCount"]))
			return;

		static::record($event, $writer, Mode::UPDATE, "trace.model.updated", $payload);
	}

	#[Observe(ModelDeleted::class)]
	public static function onModelDeleted(ModelDeleted $event, Writer $writer): void {
		static::record($event, $writer, Mode::DELETE, "trace.model.deleted");
	}

	protected static function record(ModelEvent $event, Writer $writer, int $mode, string $message, ?array $payload = null): void {
		$modelClass = $event -> modelClass();
		$instanceID = intval($event -> context["instance"] ?? 0);

		if ($modelClass === "" || $instanceID <= 0)
			return;

		if (static::isIgnored($modelClass))
			return;

		$writer -> write(array(
			"component" => static::componentForModel($modelClass),
			"scope" => "model",
			"mode" => $mode,
			"level" => "info",
			"message" => $message,
			"targetClass" => $modelClass,
			"targetID" => $instanceID,
			"payload" => $payload,
			"created" => intval($event -> created ?? time())
		));
	}

	/**
	 * How many fields the save changed and what each went from and to. The
	 * count doubles as the `{changeCount}` placeholder of the message.
	 *
	 * @return	array{changeCount: int, changes: array<string, array{old: mixed, new: mixed}>}
	 */
	protected static function changePayload(ModelUpdated $event): array {
		$changes = array();

		foreach ($event -> changes() as $field => $change) {
			if (in_array($field, static::IGNORED_CHANGE_FIELDS, true))
				continue;

			$changes[$field] = array(
				"old" => static::summarizeChangeValue($change["old"] ?? null),
				"new" => static::summarizeChangeValue($change["new"] ?? null)
			);
		}

		return array(
			"changeCount" => count($changes),
			"changes" => $changes
		);
	}

	/**
	 * Delta values are the save values, so scalars in practice. Anything else
	 * is recorded by type rather than expanded.
	 */
	protected static function summarizeChangeValue(mixed $value): mixed {
		if ($value === null || is_bool($value) || is_int($value) || is_float($value))
			return $value;

		if (!is_scalar($value))
			return "[" . get_debug_type($value) . "]";

		$value = strval($value);

		return (mb_strlen($value) > static::CHANGE_VALUE_LIMIT)
			? mb_substr($value, 0, static::CHANGE_VALUE_LIMIT) . "..."
			: $value;
	}

	protected static function componentForModel(string $modelClass): string {
		return str_starts_with($modelClass, "Blink\\")
			? Trace::CORE
			: Trace::APP;
	}
}
