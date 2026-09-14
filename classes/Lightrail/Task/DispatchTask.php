<?php

namespace Blink\Lightrail\Task;

use Blink\Lightrail;
use Blink\Lightrail\ExecutionMode;
use Blink\Ticker\Task;

/**
 * Run a stored dispatch's background handlers, on a runner.
 *
 * Carries only the dispatch id: the event itself is read back out of the
 * dispatch row, so a payload too large for a queue row is never a problem and
 * the runner always works from what was actually recorded.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DispatchTask extends Task {
	public int $dispatchID;

	public string $executionMode;

	public function __construct(int $dispatchID, string $executionMode = ExecutionMode::BACKGROUND) {
		$this -> dispatchID = $dispatchID;
		$this -> executionMode = ExecutionMode::normalize($executionMode);
		$this -> queue = \CONFIG::$LIGHTRAIL_QUEUE;
	}

	public function run(): void {
		Lightrail::coordinator() -> dispatchPendingNow($this -> dispatchID);
	}

	/**
	 * One queued run per dispatch. Also what lets the coordinator withdraw a
	 * queued run when the dispatch is handled somewhere else first.
	 */
	public function key(): ?string {
		return static::keyFor($this -> dispatchID);
	}

	public static function keyFor(int $dispatchID): string {
		return "lightrail:dispatch:{$dispatchID}";
	}

	public function label(): string {
		return "lightrail dispatch #{$this -> dispatchID}";
	}
}
