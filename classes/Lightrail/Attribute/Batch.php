<?php

namespace Blink\Lightrail\Attribute;

use Attribute;

/**
 * Collect matching events and call the handler once with all of them.
 *
 * Instead of running per event, matching events pile up in a durable buffer
 * and a debounced task hands the handler the whole
 * {@see \Blink\Lightrail\Event\EventBatch}. A burst of a hundred saves becomes
 * one call.
 *
 * The handler takes the batch rather than an event:
 *
 * ```php
 * #[Observe(ModelSaved::class)]
 * #[Batch(window: 60, dedupe: "context.instance")]
 * public static function onChange(EventBatch $batch): void { ... }
 * ```
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Batch {
	/**
	 * Seconds to wait for more events before flushing. Each new event during
	 * the window does not extend it: the flush already queued stands.
	 */
	public int $window;

	/**
	 * Flush early once this many events are buffered, so a busy key does not
	 * sit growing for the whole window. Zero waits for the window regardless.
	 */
	public int $maxSize;

	/**
	 * Dot path whose value splits events into independent batches, one flush
	 * per distinct value. Null puts every matching event in one batch.
	 */
	public ?string $key;

	/**
	 * Dot path used to collapse repeats inside a flush, keeping the latest of
	 * each. `context.instance` turns twenty saves of one row into one.
	 */
	public ?string $dedupe;

	public function __construct(int $window = 60, int $maxSize = 500, ?string $key = null, ?string $dedupe = null) {
		$this -> window = max(0, $window);
		$this -> maxSize = max(0, $maxSize);
		$this -> key = ($key === null || $key === "") ? null : $key;
		$this -> dedupe = ($dedupe === null || $dedupe === "") ? null : $dedupe;
	}

	public function toArray(): array {
		return array(
			"window" => $this -> window,
			"maxSize" => $this -> maxSize,
			"key" => $this -> key,
			"dedupe" => $this -> dedupe
		);
	}
}
