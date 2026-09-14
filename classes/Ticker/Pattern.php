<?php

namespace Blink\Ticker;

use Blink\Ticker\Exception\InvalidPattern;

/**
 * When a recurring entry is due, in the five-field cron notation.
 *
 * ```
 * minute  hour  day-of-month  month  day-of-week
 * ```
 *
 * Each field takes `*`, a number, a range `a-b`, a step `* / n` or `a-b/n`,
 * and a comma-separated list of any of those. Months and weekdays also take
 * their three-letter names. Sunday is both `0` and `7`.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Pattern {
	const MACROS = array(
		"@yearly" => "0 0 1 1 *",
		"@annually" => "0 0 1 1 *",
		"@monthly" => "0 0 1 * *",
		"@weekly" => "0 0 * * 0",
		"@daily" => "0 0 * * *",
		"@midnight" => "0 0 * * *",
		"@hourly" => "0 * * * *"
	);

	const MONTHS = array(
		"jan" => 1, "feb" => 2, "mar" => 3, "apr" => 4, "may" => 5, "jun" => 6,
		"jul" => 7, "aug" => 8, "sep" => 9, "oct" => 10, "nov" => 11, "dec" => 12
	);

	const DAYS = array(
		"sun" => 0, "mon" => 1, "tue" => 2, "wed" => 3, "thu" => 4, "fri" => 5, "sat" => 6
	);

	/**
	 * Bounds and name table for each field, in order.
	 */
	const FIELDS = array(
		array( "minute", 0, 59, null ),
		array( "hour", 0, 23, null ),
		array( "day", 1, 31, null ),
		array( "month", 1, 12, self::MONTHS ),
		array( "weekday", 0, 7, self::DAYS )
	);

	public readonly string $expression;

	/**
	 * Allowed values per field, as a set keyed by value.
	 *
	 * @var array<int, array<int, bool>>
	 */
	protected array $allowed = array();

	/**
	 * Whether each field was written as `*`. Vixie cron treats day-of-month
	 * and day-of-week as alternatives whenever both are restricted, and this
	 * is what tells them apart.
	 *
	 * @var array<int, bool>
	 */
	protected array $wildcard = array();

	public function __construct(string $expression) {
		$this -> expression = trim($expression);
		$this -> compile();
	}

	public static function of(string $expression): static {
		return new static($expression);
	}

	/**
	 * Whether the pattern covers the given moment. Seconds are ignored, since
	 * the finest a cron field goes is a minute.
	 */
	public function matches(int $timestamp = null): bool {
		$timestamp = $timestamp ?? time();
		$parts = array(
			(int) date("i", $timestamp),
			(int) date("G", $timestamp),
			(int) date("j", $timestamp),
			(int) date("n", $timestamp),
			(int) date("w", $timestamp)
		);

		foreach (array( 0, 1, 3 ) as $field) {
			if (!isset($this -> allowed[$field][$parts[$field]]))
				return false;
		}

		$dayMatches = isset($this -> allowed[2][$parts[2]]);
		$weekdayMatches = isset($this -> allowed[4][$parts[4]]);

		// With both restricted, cron runs when either one matches, which is
		// what makes "the 1st, and every Monday" expressible at all. With one
		// wildcarded the other simply has to match.
		if (!$this -> wildcard[2] && !$this -> wildcard[4])
			return $dayMatches || $weekdayMatches;

		return $dayMatches && $weekdayMatches;
	}

	/**
	 * The next minute at or after `$from` that this pattern covers.
	 *
	 * Walks minute by minute, giving up after four years, which is longer than
	 * any satisfiable pattern needs and short enough to end an unsatisfiable
	 * one (the 30th of February).
	 */
	public function next(int $from = null): ?int {
		$from = $from ?? time();

		// Aligned to the minute, since that is the resolution it matches at.
		$cursor = $from - ($from % 60) + 60;
		$limit = $cursor + (4 * 366 * 86400);

		while ($cursor < $limit) {
			if ($this -> matches($cursor))
				return $cursor;

			$cursor += 60;
		}

		return null;
	}

	public function __toString(): string {
		return $this -> expression;
	}

	protected function compile(): void {
		$expression = strtolower($this -> expression);

		if (isset(static::MACROS[$expression]))
			$expression = static::MACROS[$expression];

		$fields = preg_split("/\s+/", trim($expression));

		if (count($fields) !== 5) {
			throw new InvalidPattern(
				$this -> expression,
				"A pattern needs exactly five fields: minute, hour, day of month, month and day of week."
			);
		}

		foreach (static::FIELDS as $index => list($name, $min, $max, $names)) {
			$this -> wildcard[$index] = ($fields[$index] === "*");
			$this -> allowed[$index] = $this -> compileField($fields[$index], $name, $min, $max, $names);
		}

		// Sunday is 0 and 7 in cron, but only 0 in `date("w")`.
		if (isset($this -> allowed[4][7]))
			$this -> allowed[4][0] = true;
	}

	/**
	 * @return	array<int, bool>
	 */
	protected function compileField(string $field, string $name, int $min, int $max, ?array $names): array {
		$allowed = array();

		foreach (explode(",", $field) as $part) {
			$part = trim($part);

			if ($part === "")
				throw new InvalidPattern($this -> expression, "The {$name} field has an empty entry.");

			$step = 1;

			if (str_contains($part, "/")) {
				list($part, $stepText) = explode("/", $part, 2);
				$step = (int) $stepText;

				if ($step < 1)
					throw new InvalidPattern($this -> expression, "The {$name} field has a step of {$stepText}, which has to be at least 1.");
			}

			if ($part === "*") {
				$from = $min;
				$to = $max;
			} else if (str_contains($part, "-")) {
				list($fromText, $toText) = explode("-", $part, 2);
				$from = $this -> value($fromText, $name, $min, $max, $names);
				$to = $this -> value($toText, $name, $min, $max, $names);

				if ($from > $to)
					throw new InvalidPattern($this -> expression, "The {$name} field has the range {$part} the wrong way round.");
			} else {
				$from = $to = $this -> value($part, $name, $min, $max, $names);
			}

			for ($value = $from; $value <= $to; $value += $step)
				$allowed[$value] = true;
		}

		return $allowed;
	}

	protected function value(string $token, string $name, int $min, int $max, ?array $names): int {
		$token = trim($token);

		if ($names !== null && isset($names[substr($token, 0, 3)]))
			return $names[substr($token, 0, 3)];

		if (!preg_match("/^\d+$/", $token))
			throw new InvalidPattern($this -> expression, "The {$name} field does not understand \"{$token}\".");

		$value = (int) $token;

		if ($value < $min || $value > $max)
			throw new InvalidPattern($this -> expression, "The {$name} field takes {$min} to {$max}, and got {$value}.");

		return $value;
	}
}
