<?php

namespace Blink;

use Blink\Exception\ClassNotFound;
use Blink\Exception\CodingError;
use Blink\Lightrail\Event\BaseEvent;
use Blink\Lightrail\Event\GenericEvent;
use Blink\Lightrail\EventCoordinator;
use Blink\Lightrail\ExecutionMode;
use Blink\Lightrail\Registry\ListenerRegistry;

/**
 * Blink's event bus.
 *
 * Emit something that happened and whoever is watching reacts, each on its own
 * terms: now, after the response, on a runner, or collected into a batch.
 *
 * ```php
 * Lightrail::emitEvent(new InvoicePaid(["invoice" => $invoice -> id]));
 * ```
 *
 * Handlers are declared as attributes on an observer class, which a module
 * lists in `observers()`:
 *
 * ```php
 * class InvoiceObserver {
 * 	#[Observe(InvoicePaid::class)]
 * 	#[ExecuteIn(ExecutionMode::BACKGROUND)]
 * 	public static function sendReceipt(InvoicePaid $event): void { ... }
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
class Lightrail {
	public static function coordinator(): EventCoordinator {
		return Essence::make(EventCoordinator::class);
	}

	public static function registry(): ListenerRegistry {
		return Essence::make(ListenerRegistry::class);
	}

	/**
	 * Register a single handler, without an observer class around it.
	 *
	 * The callable has to be static: a background handler is resolved in
	 * another process, where a closure means nothing.
	 *
	 * @param	class-string<BaseEvent>		$eventClass
	 * @param	string|array				$handler
	 */
	public static function registerListener(
		string $eventClass,
		string|array $handler,
		int $priority = 0,
		array $context = array(),
		string $executionMode = ExecutionMode::IMMEDIATE,
		array $middleware = array(),
		array $excludedMiddleware = array()
	): void {
		static::coordinator() -> registerListener(
			$eventClass, $handler, $priority, $context, $executionMode, $middleware, $excludedMiddleware
		);
	}

	/**
	 * Register a class whose methods carry `#[Observe]` attributes.
	 *
	 * A module normally declares these in `observers()` instead, which the
	 * registry can rebuild from without being asked.
	 */
	public static function registerObserver(string $observerClass): void {
		static::coordinator() -> registerObserverClass($observerClass);
	}

	public static function rebuildCache(): void {
		static::coordinator() -> rebuildCache();
	}

	public static function clearCache(): void {
		static::registry() -> purgeCache();
	}

	/**
	 * Emit an event and let each handler run where it asked to.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public static function emitEvent(BaseEvent $event): array {
		return static::coordinator() -> emit($event);
	}

	/**
	 * Build an event from its class and emit it.
	 *
	 * @param	class-string<BaseEvent>		$eventClass
	 * @return	array<int, array<string, mixed>>
	 */
	public static function emit(string $eventClass, array $context = array()): array {
		return static::emitEvent(static::makeEvent($eventClass, $context));
	}

	/**
	 * Emit an unnamed event. For a one-off signal not worth a class.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public static function emitGeneric(array $context = array()): array {
		return static::emitEvent(new GenericEvent($context));
	}

	/**
	 * Run every handler of an event now, ignoring the modes they asked for.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public static function dispatch(BaseEvent $event): array {
		return static::coordinator() -> dispatch($event);
	}

	/**
	 * @param	class-string<BaseEvent>		$eventClass
	 */
	protected static function makeEvent(string $eventClass, array $context): BaseEvent {
		if (!class_exists($eventClass))
			throw new ClassNotFound($eventClass);

		if (!is_subclass_of($eventClass, BaseEvent::class)) {
			throw new CodingError(
				"<code>{$eventClass}</code> is not a Lightrail event.",
				"An event has to extend <code>\\Blink\\Lightrail\\Event\\BaseEvent</code>."
			);
		}

		return new $eventClass($context);
	}
}
