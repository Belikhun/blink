<?php

namespace Blink\Ticker;

use Blink\Exception\InvalidDefinition;
use Blink\Ticker\Orbit\Entry;

/**
 * Everything that comes round on a schedule.
 *
 * A module declares its recurring work in `orbits()`, and the collected list
 * is what `ticker:tick` walks once a minute.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Orbit {
	/** @var Entry[] */
	protected array $entries = array();

	/**
	 * Schedule a task.
	 *
	 * @param	class-string<Task>|Task		$task
	 */
	public function task(string|Task $task): Entry {
		if (is_string($task) && !is_subclass_of($task, Task::class))
			throw new InvalidDefinition($task, Task::class);

		$name = is_string($task)
			? $task
			: $task -> label();

		return $this -> add(new Entry(Entry::KIND_TASK, $task, static::slug($name)));
	}

	/**
	 * Schedule a console command, written as it would be typed.
	 */
	public function command(string $command): Entry {
		return $this -> add(new Entry(Entry::KIND_COMMAND, $command, static::slug($command)));
	}

	/**
	 * @return	Entry[]
	 */
	public function entries(): array {
		return $this -> entries;
	}

	/**
	 * @return	?Entry
	 */
	public function find(string $name): ?Entry {
		foreach ($this -> entries as $entry) {
			if ($entry -> name === $name)
				return $entry;
		}

		return null;
	}

	protected function add(Entry $entry): Entry {
		$this -> entries[] = $entry;
		return $entry;
	}

	/**
	 * A stable, readable identifier. It ends up in a database column and in
	 * `ticker:orbits`, so it has to stay the same between runs.
	 */
	protected static function slug(string $name): string {
		$name = strtolower(str_replace("\\", ".", trim($name)));
		$name = preg_replace("/[^a-z0-9:._-]+/", "-", $name);

		return trim((string) $name, "-");
	}
}
