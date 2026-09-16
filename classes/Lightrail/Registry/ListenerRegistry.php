<?php

namespace Blink\Lightrail\Registry;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Blink\Exception\CodingError;
use Blink\Lightrail\Attribute\Batch;
use Blink\Lightrail\Attribute\Context;
use Blink\Lightrail\Attribute\ExecuteIn;
use Blink\Lightrail\Attribute\Middleware;
use Blink\Lightrail\Attribute\Observe;
use Blink\Lightrail\Attribute\Priority;
use Blink\Lightrail\Attribute\WithoutMiddleware;
use Blink\Lightrail\Event\BaseEvent;
use Blink\Lightrail\ExecutionMode;

/**
 * Which handlers answer to which events.
 *
 * Built by reflecting over every observer class a module declares, then
 * cached. Anything registered at runtime is kept apart from that, so a rebuild
 * discards the discovery without discarding what the running request added.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ListenerRegistry {
	/**
	 * Every handler, discovered and runtime together.
	 *
	 * @var HandlerDescriptor[]
	 */
	protected array $handlers = array();

	/**
	 * Handlers registered by this process rather than discovered.
	 *
	 * Held separately because a rebuild throws the discovered set away, and a
	 * listener the running request just added has nowhere else to come back
	 * from.
	 *
	 * @var HandlerDescriptor[]
	 */
	protected array $runtimeHandlers = array();

	/**
	 * @var array<string, HandlerDescriptor[]>
	 */
	protected array $handlersByEvent = array();

	/**
	 * Observer classes to reflect over, mapped to the module that declared
	 * them.
	 *
	 * @var array<string, ?string>
	 */
	protected array $observerClasses = array();

	/**
	 * Observer classes added at runtime, kept across a rebuild for the same
	 * reason as the runtime handlers.
	 *
	 * @var array<string, ?string>
	 */
	protected array $runtimeObservers = array();

	protected bool $loaded = false;

	public function __construct(
		protected ?RegistryCache $cache = null
	) {
		$this -> cache = $cache ?? RegistryCache::make();
	}

	/**
	 * Register a single handler, without an observer class around it.
	 *
	 * @param	class-string<BaseEvent>		$eventClass
	 * @param	string|array				$handler	A callable as `Class::method` or `[Class, "method"]`.
	 */
	public function registerListener(
		string $eventClass,
		string|array $handler,
		?string $module = null,
		int $priority = 0,
		array $context = array(),
		string $executionMode = ExecutionMode::IMMEDIATE,
		array $middleware = array(),
		array $excludedMiddleware = array()
	): static {
		// Settle the cached set first: `load()` replaces the handler list
		// wholesale, so a listener added before the first load would vanish.
		$this -> load();

		$descriptor = new HandlerDescriptor(
			$eventClass,
			$this -> normalizeCallable($handler),
			$module,
			false,
			$priority,
			$context,
			$executionMode,
			"",
			$middleware,
			$excludedMiddleware
		);

		// The key is a digest of what the handler is and how it runs, so an
		// identical registration is indistinguishable from the first and
		// would only make the handler run twice. Observer classes are
		// deduplicated the same way.
		foreach ($this -> handlers as $existing) {
			if ($existing -> key === $descriptor -> key)
				return $this;
		}

		$this -> handlers[] = $descriptor;
		$this -> runtimeHandlers[] = $descriptor;

		// Drop the per-event memo so an event class already dispatched once
		// recomputes its list and picks this up.
		$this -> handlersByEvent = array();
		return $this;
	}

	/**
	 * Register a class whose methods carry {@see Observe} attributes.
	 */
	public function registerObserverClass(string $className, ?string $module = null): static {
		if (isset($this -> observerClasses[$className]))
			return $this;

		$this -> observerClasses[$className] = $module;
		$this -> runtimeObservers[$className] = $module;

		return $this;
	}

	/**
	 * Collect the observer classes every loaded module declares.
	 */
	public function registerModuleObservers(): static {
		foreach (\Blink::modules() as $class => $module) {
			foreach ($module -> observers() as $observer)
				$this -> observerClasses[$observer] = $class;
		}

		return $this;
	}

	/**
	 * Handlers watching an event class, in the order they should run.
	 *
	 * @return	HandlerDescriptor[]
	 */
	public function handlersFor(string $eventClass): array {
		$this -> load();

		if (isset($this -> handlersByEvent[$eventClass]))
			return $this -> handlersByEvent[$eventClass];

		$matches = array();

		foreach ($this -> handlers as $descriptor) {
			// `is_a` rather than equality, so a handler watching a base event
			// also sees everything derived from it.
			if (is_a($eventClass, $descriptor -> eventClass, true))
				$matches[] = $descriptor;
		}

		$matches = $this -> sortHandlers($matches);
		$this -> handlersByEvent[$eventClass] = $matches;
		$this -> store();

		return $matches;
	}

	/**
	 * Handlers watching this particular event, context filters applied.
	 *
	 * @return	HandlerDescriptor[]
	 */
	public function handlersForEvent(BaseEvent $event): array {
		return array_values(array_filter(
			$this -> handlersFor($event::class),
			fn (HandlerDescriptor $descriptor) => $this -> matchesEvent($event, $descriptor)
		));
	}

	public function matchesEvent(BaseEvent $event, HandlerDescriptor $descriptor): bool {
		return $this -> contextSubsetMatches($descriptor -> context, $event -> context);
	}

	/**
	 * @return	HandlerDescriptor[]
	 */
	public function allHandlers(): array {
		$this -> load();
		return $this -> sortHandlers($this -> handlers);
	}

	/**
	 * Find a handler by its stable key.
	 *
	 * How a background dispatch recovers the handler it was queued for: the
	 * queued row carries the key, not the descriptor.
	 */
	public function descriptorByKey(string $key): ?HandlerDescriptor {
		if (empty($key))
			return null;

		$this -> load();

		foreach ($this -> handlers as $descriptor) {
			if ($descriptor -> key === $key)
				return $descriptor;
		}

		return null;
	}

	/**
	 * Throw away the discovery and do it again.
	 */
	public function rebuild(): void {
		// Marked loaded first: the registration below goes through
		// `registerListener()`, whose own `load()` would otherwise recurse back
		// into this rebuild with the cache still cold.
		$this -> loaded = true;

		$this -> handlers = array();
		$this -> handlersByEvent = array();
		$this -> observerClasses = $this -> runtimeObservers;

		$this -> registerModuleObservers();
		$this -> discoverObserverMethods();

		// Put back what this process registered by hand. Discovery cannot find
		// these, and losing them would silently stop handlers that were
		// working a moment ago.
		foreach ($this -> runtimeHandlers as $descriptor)
			$this -> handlers[] = $descriptor;

		$this -> store();
	}

	public function purgeCache(): void {
		$this -> cache -> purge();
		$this -> loaded = false;
	}

	protected function load(): void {
		if ($this -> loaded)
			return;

		$cached = $this -> cache -> load($this -> cache -> getVersion());

		if (empty($cached)) {
			$this -> rebuild();
			return;
		}

		$this -> handlers = array_map(
			fn (array $row) => HandlerDescriptor::fromArray($row),
			$cached["all"]
		);

		$this -> handlersByEvent = array();

		foreach ($cached["byEvent"] as $eventClass => $items) {
			$this -> handlersByEvent[$eventClass] = array_map(
				fn (array $row) => HandlerDescriptor::fromArray($row),
				$items
			);
		}

		$this -> loaded = true;
	}

	/**
	 * Read the attributes off every observer method and turn them into
	 * descriptors.
	 */
	protected function discoverObserverMethods(): void {
		foreach ($this -> observerClasses as $className => $module) {
			if (!class_exists($className))
				continue;

			$reflection = new ReflectionClass($className);
			$methods = $reflection -> getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC);

			foreach ($methods as $method) {
				// Skip anything inherited: the parent's own registration
				// already covers it.
				if ($method -> class !== $className)
					continue;

				$observes = $method -> getAttributes(Observe::class, ReflectionAttribute::IS_INSTANCEOF);

				if (empty($observes))
					continue;

				$priority = $this -> firstAttribute($method, Priority::class) ?-> priority ?? 0;
				$context = $this -> firstAttribute($method, Context::class) ?-> context ?? array();
				$executionMode = $this -> firstAttribute($method, ExecuteIn::class) ?-> mode ?? ExecutionMode::IMMEDIATE;

				$batch = array();
				$batchAttribute = $this -> firstAttribute($method, Batch::class);

				if ($batchAttribute !== null) {
					$batch = $batchAttribute -> toArray();

					// A batched handler never runs per event, whatever else it
					// asked for. The flush is what drives it.
					$executionMode = ExecutionMode::BATCHED;
				}

				$middleware = array();
				foreach ($method -> getAttributes(Middleware::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute)
					$middleware = array_merge($middleware, $attribute -> newInstance() -> middleware);

				$excluded = array();
				foreach ($method -> getAttributes(WithoutMiddleware::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute)
					$excluded = array_merge($excluded, $attribute -> newInstance() -> middleware);

				foreach ($observes as $attribute) {
					$eventClass = $attribute -> newInstance() -> eventClass;

					if (empty($eventClass))
						continue;

					$this -> handlers[] = new HandlerDescriptor(
						$eventClass,
						"{$className}::{$method -> getName()}",
						$module,
						true,
						(int) $priority,
						is_array($context) ? $context : array(),
						$executionMode,
						"",
						$middleware,
						$excluded,
						$batch
					);
				}
			}
		}
	}

	/**
	 * @template T of object
	 * @param	class-string<T>		$attribute
	 * @return	?T
	 */
	protected function firstAttribute(ReflectionMethod $method, string $attribute): ?object {
		$attributes = $method -> getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);

		return empty($attributes)
			? null
			: $attributes[0] -> newInstance();
	}

	/**
	 * Highest priority first, registration order breaking ties.
	 *
	 * @param	HandlerDescriptor[]		$handlers
	 * @return	HandlerDescriptor[]
	 */
	protected function sortHandlers(array $handlers): array {
		$handlers = array_values($handlers);
		$order = array();

		foreach ($handlers as $index => $handler)
			$order[spl_object_id($handler)] = $index;

		usort($handlers, function (HandlerDescriptor $a, HandlerDescriptor $b) use ($order) {
			if ($a -> priority === $b -> priority)
				return $order[spl_object_id($a)] <=> $order[spl_object_id($b)];

			return $b -> priority <=> $a -> priority;
		});

		return $handlers;
	}

	/**
	 * Whether every key the handler asked for is present with the same value.
	 * Nested arrays are matched the same way, one level at a time.
	 */
	protected function contextSubsetMatches(array $expected, array $actual): bool {
		foreach ($expected as $key => $value) {
			if (!array_key_exists($key, $actual))
				return false;

			if (is_array($value)) {
				if (!is_array($actual[$key]) || !$this -> contextSubsetMatches($value, $actual[$key]))
					return false;

				continue;
			}

			if ($actual[$key] !== $value)
				return false;
		}

		return true;
	}

	protected function store(): void {
		// Runtime listeners are left out. The cache is a record of what
		// discovery found, and the next process discovers the same again;
		// writing a hand-registered listener into it would have that process
		// load a copy and add its own on top, so the handler would run twice
		// on the second request, three times on the third, and the file would
		// grow to match.
		$runtime = array();

		foreach ($this -> runtimeHandlers as $descriptor)
			$runtime[spl_object_id($descriptor)] = true;

		$discovered = fn (array $items) => array_values(array_filter(
			$items,
			fn (HandlerDescriptor $handler) => !isset($runtime[spl_object_id($handler)])
		));

		$byEvent = array();

		foreach ($this -> handlersByEvent as $eventClass => $items) {
			$byEvent[$eventClass] = array_map(
				fn (HandlerDescriptor $handler) => $handler -> toArray(),
				$discovered($items)
			);
		}

		$this -> cache -> store(
			$this -> cache -> getVersion(),
			array_map(
				fn (HandlerDescriptor $handler) => $handler -> toArray(),
				$discovered($this -> handlers)
			),
			$byEvent
		);
	}

	protected function normalizeCallable(string|array $handler): string {
		if (is_string($handler))
			return $handler;

		if (count($handler) === 2) {
			$class = is_object($handler[0])
				? get_class($handler[0])
				: (string) $handler[0];

			return "{$class}::{$handler[1]}";
		}

		throw new CodingError(
			"Lightrail listeners have to be static.",
			"Pass a <code>Class::method</code> string or a <code>[Class::class, \"method\"]</code> pair. "
				. "A closure cannot be stored, and a background handler is resolved in another process."
		);
	}
}
