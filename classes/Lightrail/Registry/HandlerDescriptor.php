<?php

namespace Blink\Lightrail\Registry;

use Blink\Lightrail\ExecutionMode;

/**
 * Everything the registry knows about one handler.
 *
 * Flat and serializable on purpose: the registry is cached, and a background
 * dispatch reads a handler back out of storage long after the request that
 * registered it has gone.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class HandlerDescriptor {
	public function __construct(
		public string $eventClass,
		public string $callable,
		public ?string $module = null,
		public bool $observer = false,
		public int $priority = 0,
		public array $context = array(),
		public string $executionMode = ExecutionMode::IMMEDIATE,
		public string $key = "",
		public array $middleware = array(),
		public array $excludedMiddleware = array(),
		public array $batch = array()
	) {
		$this -> executionMode = ExecutionMode::normalize($this -> executionMode);

		if (empty($this -> key))
			$this -> key = static::buildKey($this);
	}

	/**
	 * A stable name for this handler, used to resume a background dispatch and
	 * to address a batch buffer.
	 *
	 * Hashed from what the handler is rather than where it sits in a list, so
	 * it survives another handler being added ahead of it.
	 */
	public static function buildKey(HandlerDescriptor $descriptor): string {
		return sha1(json_encode(array(
			"eventClass" => $descriptor -> eventClass,
			"callable" => $descriptor -> callable,
			"module" => $descriptor -> module,
			"observer" => $descriptor -> observer,
			"priority" => $descriptor -> priority,
			"context" => $descriptor -> context,
			"executionMode" => $descriptor -> executionMode
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	public function toArray(): array {
		return array(
			"eventClass" => $this -> eventClass,
			"callable" => $this -> callable,
			"module" => $this -> module,
			"observer" => $this -> observer,
			"priority" => $this -> priority,
			"context" => $this -> context,
			"executionMode" => $this -> executionMode,
			"key" => $this -> key,
			"middleware" => $this -> middleware,
			"excludedMiddleware" => $this -> excludedMiddleware,
			"batch" => $this -> batch
		);
	}

	public static function fromArray(array $data): static {
		return new static(
			(string) ($data["eventClass"] ?? ""),
			(string) ($data["callable"] ?? ""),
			!empty($data["module"]) ? (string) $data["module"] : null,
			!empty($data["observer"]),
			(int) ($data["priority"] ?? 0),
			is_array($data["context"] ?? null) ? $data["context"] : array(),
			(string) ($data["executionMode"] ?? ExecutionMode::IMMEDIATE),
			(string) ($data["key"] ?? ""),
			is_array($data["middleware"] ?? null) ? $data["middleware"] : array(),
			is_array($data["excludedMiddleware"] ?? null) ? $data["excludedMiddleware"] : array(),
			is_array($data["batch"] ?? null) ? $data["batch"] : array()
		);
	}

	/**
	 * Whether this handler is batched, which is true whenever the attribute
	 * gave it a batch configuration.
	 */
	public function batched(): bool {
		return $this -> executionMode === ExecutionMode::BATCHED && !empty($this -> batch);
	}

	/**
	 * Whether an event's context satisfies the filter this handler declared.
	 */
	public function matches(array $context): bool {
		foreach ($this -> context as $key => $value) {
			if (!array_key_exists($key, $context) || $context[$key] !== $value)
				return false;
		}

		return true;
	}
}
