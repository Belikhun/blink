<?php

namespace Blink\Lightrail\Event;

/**
 * Something that happened, which handlers can react to.
 *
 * An event carries a plain array of context and nothing else. That is
 * deliberate: a background or batched handler reads its event back out of the
 * database, so whatever is in here has to survive a round trip through JSON.
 * Carry ids, not models.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class BaseEvent {
	public int $created;

	public function __construct(
		public array $context = array(),
		?int $created = null
	) {
		$this -> created = $created ?? time();
	}

	public function eventClass(): string {
		return static::class;
	}

	public function toArray(): array {
		return array(
			"eventClass" => $this -> eventClass(),
			"created" => $this -> created,
			"context" => $this -> context
		);
	}

	public static function fromArray(array $data): static {
		$created = !empty($data["created"])
			? (int) $data["created"]
			: null;

		return new static(static::normalizeContext($data["context"] ?? array()), $created);
	}

	/**
	 * Flatten whatever came back from storage into plain arrays.
	 *
	 * JSON decoding hands back `stdClass` for an object, and a handler reading
	 * `$event -> context["x"]["y"]` should not have to care which side of the
	 * round trip it is on.
	 */
	protected static function normalizeContext(mixed $value): array {
		if (is_object($value))
			$value = (array) $value;

		if (!is_array($value))
			return array();

		foreach ($value as $key => $item)
			$value[$key] = static::normalizeValue($item);

		return $value;
	}

	protected static function normalizeValue(mixed $value): mixed {
		if (is_object($value))
			$value = (array) $value;

		if (is_array($value)) {
			foreach ($value as $key => $item)
				$value[$key] = static::normalizeValue($item);
		}

		return $value;
	}
}
