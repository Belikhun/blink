<?php

namespace Blink\Ether\Event;

use Blink\Lightrail\Event\BaseEvent;

/**
 * Event dispatch middleware execution context.
 *
 * Wraps a single Lightrail emission. A middleware that returns without calling
 * `$next` short-circuits the whole event: no handlers run and
 * {@see EventDispatchContext::$dispatched} stays false.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EventDispatchContext {
	/**
	 * Results returned by the dispatch destination, one entry per handler run.
	 *
	 * @var	mixed
	 */
	public mixed $result = null;

	/**
	 * Set true by the dispatch destination once handler resolution and dispatch
	 * have run. Stays false when a middleware short-circuits the emission.
	 *
	 * @var	bool
	 */
	public bool $dispatched = false;

	public function __construct(
		public BaseEvent $event,
		public string $dispatchMode
	) {}
}
