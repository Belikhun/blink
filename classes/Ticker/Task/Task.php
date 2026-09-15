<?php

namespace Blink\Ticker;

/**
 * A unit of work to be done later.
 *
 * A task is serialized when it is queued and woken up when a runner reaches
 * it, so whatever it carries has to survive `serialize()`. Hold ids rather
 * than models: the row may well have changed by the time the task runs, and
 * a stale copy is worse than a fresh read.
 *
 * ```php
 * class SendInvoice extends Ticker\Task {
 * 	public int $attempts = 3;
 * 	public int $holdoff = 60;
 *
 * 	public function __construct(protected int $invoiceId) {}
 *
 * 	public function run(): void {
 * 		Invoice::getByID($this -> invoiceId, Model::MUST_EXIST) -> send();
 * 	}
 * }
 *
 * Ticker::queue(new SendInvoice($invoice -> id));
 * ```
 *
 * Delivery is at least once, never exactly once: a runner that dies mid-task
 * leaves the claim to expire, and another runner picks the task up again. A
 * task has to be safe to run twice.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class Task {
	/**
	 * How many times to try before giving up and filing the failure.
	 */
	public int $attempts = 1;

	/**
	 * Seconds to wait before trying again.
	 *
	 * Override {@see Task::holdoffFor()} for anything cleverer, such as
	 * backing off further with each attempt.
	 */
	public int $holdoff = 0;

	/**
	 * Seconds this task is allowed to take before a runner treats it as lost.
	 * Zero falls back to `CONFIG::$TICKER_RETRY_AFTER`.
	 */
	public int $timeout = 0;

	/**
	 * Which queue to sit in. Null uses `CONFIG::$TICKER_QUEUE`.
	 */
	public ?string $queue = null;

	/**
	 * Do the work.
	 */
	abstract public function run(): void;

	/**
	 * Called once the last attempt has failed, before the failure is filed.
	 *
	 * Whatever this raises is swallowed: the task has already failed, and a
	 * failing failure handler must not hide the original.
	 */
	public function failed(\Throwable $e): void {}

	/**
	 * A key that stands for "this exact piece of work".
	 *
	 * {@see \Blink\Ticker::queueUnique()} refuses to queue a second task
	 * carrying a key that is already waiting, which is how a burst of
	 * identical requests collapses into one run. Null means every instance is
	 * its own piece of work.
	 */
	public function key(): ?string {
		return null;
	}

	/**
	 * How the task reads in a listing or a failure report.
	 */
	public function label(): string {
		return static::class;
	}

	/**
	 * How long to wait before the given attempt number.
	 *
	 * Flat by default. Override it to back off as attempts pile up, which is
	 * what a task talking to something that may be overloaded should do:
	 *
	 * ```php
	 * public function holdoffFor(int $attempt): int {
	 * 	return [ 10, 60, 300 ][$attempt - 1] ?? 300;
	 * }
	 * ```
	 *
	 * @param	int		$attempt	How many attempts have been made so far.
	 */
	public function holdoffFor(int $attempt): int {
		return max($this -> holdoff, 0);
	}
}
