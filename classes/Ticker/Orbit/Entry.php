<?php

namespace Blink\Ticker\Orbit;

use Blink\Ticker\Pattern;
use Blink\Ticker\Task;

/**
 * One recurring piece of work, and when it comes round.
 *
 * ```php
 * public function orbits(Orbit $orbit): void {
 * 	$orbit -> task(PurgeReports::class) -> everyDay("03:00") -> alone();
 * 	$orbit -> command("cache:clear") -> everyWeek();
 * }
 * ```
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Entry {
	const KIND_TASK = "task";

	const KIND_COMMAND = "command";

	/**
	 * What identifies this entry across hosts and across runs. A tick claims
	 * the minute by writing against this, so two hosts ticking together agree
	 * on which entry they are talking about.
	 */
	public string $name;

	public string $kind;

	/**
	 * A task class name, an already-built task, or a command line.
	 *
	 * @var class-string<Task>|Task|string
	 */
	public mixed $target;

	public string $expression = "* * * * *";

	/**
	 * Hold a lock for the run, so an entry that overruns its interval does not
	 * start again on top of itself.
	 */
	public bool $alone = false;

	/**
	 * Seconds the overlap lock holds before another host may take over.
	 */
	public int $aloneFor = 3600;

	/**
	 * Queue the work rather than doing it inside the tick. Keeps a long job
	 * from holding up everything else due in the same minute.
	 */
	public bool $detached = false;

	/**
	 * Extra condition checked at tick time.
	 *
	 * @var ?callable
	 */
	public $condition = null;

	public function __construct(string $kind, mixed $target, string $name) {
		$this -> kind = $kind;
		$this -> target = $target;
		$this -> name = $name;
	}

	/**
	 * Override the generated name. Two entries sharing one would claim each
	 * other's minutes, so keep it distinct.
	 */
	public function name(string $name): static {
		$this -> name = $name;
		return $this;
	}

	public function cron(string $expression): static {
		// Compiled now so a bad pattern is a boot failure rather than a
		// silently dead entry nobody notices for a week.
		new Pattern($expression);

		$this -> expression = $expression;
		return $this;
	}

	public function everyMinute(): static {
		return $this -> cron("* * * * *");
	}

	public function everyFiveMinutes(): static {
		return $this -> cron("*/5 * * * *");
	}

	public function everyTenMinutes(): static {
		return $this -> cron("*/10 * * * *");
	}

	public function everyFifteenMinutes(): static {
		return $this -> cron("*/15 * * * *");
	}

	public function everyThirtyMinutes(): static {
		return $this -> cron("*/30 * * * *");
	}

	/**
	 * Once an hour, at the given minute past.
	 */
	public function everyHour(int $minute = 0): static {
		return $this -> cron("{$minute} * * * *");
	}

	/**
	 * Once a day, at the given `HH:MM`.
	 */
	public function everyDay(string $time = "00:00"): static {
		list($hour, $minute) = static::time($time);
		return $this -> cron("{$minute} {$hour} * * *");
	}

	/**
	 * Once a week, on the given weekday and time. Zero is Sunday.
	 */
	public function everyWeek(int $weekday = 0, string $time = "00:00"): static {
		list($hour, $minute) = static::time($time);
		return $this -> cron("{$minute} {$hour} * * {$weekday}");
	}

	/**
	 * Once a month, on the given day and time.
	 */
	public function everyMonth(int $day = 1, string $time = "00:00"): static {
		list($hour, $minute) = static::time($time);
		return $this -> cron("{$minute} {$hour} {$day} * *");
	}

	public function alone(int $seconds = 3600): static {
		$this -> alone = true;
		$this -> aloneFor = max($seconds, 1);
		return $this;
	}

	public function detached(bool $detached = true): static {
		$this -> detached = $detached;
		return $this;
	}

	/**
	 * Run only when the callable agrees, checked at tick time.
	 */
	public function when(callable $condition): static {
		$this -> condition = $condition;
		return $this;
	}

	public function pattern(): Pattern {
		return new Pattern($this -> expression);
	}

	public function due(int $timestamp = null): bool {
		if (!$this -> pattern() -> matches($timestamp))
			return false;

		if ($this -> condition !== null && !($this -> condition)())
			return false;

		return true;
	}

	/**
	 * @return	array{0: int, 1: int}	Hour and minute.
	 */
	protected static function time(string $time): array {
		$parts = explode(":", trim($time));

		return array(
			(int) ($parts[0] ?? 0),
			(int) ($parts[1] ?? 0)
		);
	}
}
