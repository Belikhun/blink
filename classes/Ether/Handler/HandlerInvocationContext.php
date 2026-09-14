<?php

namespace Blink\Ether\Handler;

use Blink\Lightrail\Event\BaseEvent;
use Blink\Lightrail\Model\HandlerLog;

/**
 * Handler invocation middleware execution context.
 *
 * Wraps a single handler call. A middleware that returns without calling
 * `$next` skips the handler: its body never runs and
 * {@see HandlerInvocationContext::$invoked} stays false, so the dispatcher
 * records the handler as skipped.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class HandlerInvocationContext {
	/**
	 * Value returned by the handler, or by downstream middleware.
	 *
	 * @var	mixed
	 */
	public mixed $result = null;

	/**
	 * Set true by the invocation destination once the handler body has run.
	 * Stays false when a middleware short-circuits the call.
	 *
	 * @var	bool
	 */
	public bool $invoked = false;

	public function __construct(
		public BaseEvent $event,
		public HandlerLog $handler
	) {}
}
