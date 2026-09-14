<?php

namespace Blink;

use Blink\Trace\Builder;
use Blink\Trace\LevelRegistry;
use Blink\Trace\ModeRegistry;
use Blink\Trace\Writer;

/**
 * The audit log: who did what to which record, kept in the `traces` table.
 *
 * ```php
 * Trace::info(Trace::APP, "order.shipped")
 *     -> mode("update")
 *     -> target($order)
 *     -> payload([ "carrier" => $carrier ])
 *     -> record();
 * ```
 *
 * Entries are queued and written in one insert when the request ends, so
 * recording one costs the request nothing. A module that wants more levels or
 * modes type-hints {@see LevelRegistry} or {@see ModeRegistry} in its
 * `attach()` and registers them there.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
final class Trace {
	/**
	 * Component for entries blink records about itself.
	 */
	const CORE = "blink";

	/**
	 * Component for entries the application records.
	 */
	const APP = "app";

	/**
	 * Start an entry at the default level.
	 */
	public static function entry(string $component, string $message): Builder {
		return new Builder($component, $message);
	}

	public static function debug(string $component, string $message): Builder {
		return static::entry($component, $message) -> level("debug");
	}

	public static function info(string $component, string $message): Builder {
		return static::entry($component, $message) -> level("info");
	}

	public static function notice(string $component, string $message): Builder {
		return static::entry($component, $message) -> level("notice");
	}

	public static function warning(string $component, string $message): Builder {
		return static::entry($component, $message) -> level("warning");
	}

	public static function error(string $component, string $message): Builder {
		return static::entry($component, $message) -> level("error");
	}

	public static function critical(string $component, string $message): Builder {
		return static::entry($component, $message) -> level("critical");
	}

	public static function alert(string $component, string $message): Builder {
		return static::entry($component, $message) -> level("alert");
	}

	public static function emergency(string $component, string $message): Builder {
		return static::entry($component, $message) -> level("emergency");
	}

	/**
	 * Write every queued entry now.
	 *
	 * @return	int		Entries written.
	 */
	public static function flush(): int {
		return static::writer() -> flush();
	}

	public static function writer(): Writer {
		return Essence::make(Writer::class);
	}

	public static function levels(): LevelRegistry {
		return Essence::make(LevelRegistry::class);
	}

	public static function modes(): ModeRegistry {
		return Essence::make(ModeRegistry::class);
	}
}
