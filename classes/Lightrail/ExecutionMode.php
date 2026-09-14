<?php

namespace Blink\Lightrail;

/**
 * When a handler runs relative to the event that triggered it.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ExecutionMode {
	/**
	 * Right away, inside the emit. Whatever it raises reaches the caller.
	 */
	public const IMMEDIATE = "immediate";

	/**
	 * After the response has gone out, from the shutdown handler. For work the
	 * caller should not wait for but which still belongs to this request.
	 */
	public const SHUTDOWN = "shutdown";

	/**
	 * On a Ticker runner, in another process entirely. Survives this request
	 * ending, and retries if it fails.
	 */
	public const BACKGROUND = "background";

	/**
	 * Not once per event. Matching events pile up in a durable buffer and the
	 * handler is called once with the whole lot, so a burst collapses into a
	 * single call. See {@see \Blink\Lightrail\Attribute\Batch}.
	 */
	public const BATCHED = "batched";

	/**
	 * @return	string[]
	 */
	public static function all(): array {
		return array(
			static::IMMEDIATE,
			static::SHUTDOWN,
			static::BACKGROUND,
			static::BATCHED
		);
	}

	/**
	 * Anything unrecognised becomes immediate, which is the mode that always
	 * works: a typo should not leave a handler silently never running.
	 */
	public static function normalize(?string $mode): string {
		$mode = strtolower(trim((string) $mode));

		return in_array($mode, static::all(), true)
			? $mode
			: static::IMMEDIATE;
	}

	/**
	 * Whether the mode needs a stored dispatch row to resume from, which is
	 * what `CONFIG::$LIGHTRAIL_TRAIL` provides.
	 */
	public static function needsTrail(string $mode): bool {
		return in_array(static::normalize($mode), array( static::BACKGROUND, static::BATCHED ), true);
	}
}
