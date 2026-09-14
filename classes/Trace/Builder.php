<?php

namespace Blink\Trace;

use Blink\Essence;
use Blink\Model;
use Blink\Model\UserBase;

/**
 * Assemble one audit entry and hand it to the {@see Writer}.
 *
 * Component and message come from the constructor and the level defaults to
 * `info`. {@see record()} more than once returns the same entry.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
final class Builder {
	protected array $entry = array();

	protected ?Entry $recorded = null;

	public function __construct(string $component, string $message) {
		$this -> entry["component"] = $component;
		$this -> entry["message"] = $message;
		$this -> entry["level"] = "info";
	}

	public function level(string|int $level): static {
		$this -> entry["level"] = $level;
		return $this;
	}

	public function mode(string|int $mode): static {
		$this -> entry["mode"] = $mode;
		return $this;
	}

	public function scope(?string $scope): static {
		$this -> entry["scope"] = $scope;
		return $this;
	}

	/**
	 * A short indexed key for the subject of the entry, up to 32 characters,
	 * so related entries can be found by exact match rather than by searching
	 * the payload.
	 */
	public function field(?string $field): static {
		$this -> entry["field"] = $field;
		return $this;
	}

	/**
	 * Who did it. Left unset, the signed-in user is recorded.
	 */
	public function actor(int|UserBase|null $actor): static {
		$this -> entry["actor"] = $actor;
		return $this;
	}

	public function source(?Model $source): static {
		$this -> entry["source"] = $source;
		return $this;
	}

	public function target(?Model $target): static {
		$this -> entry["target"] = $target;
		return $this;
	}

	public function payload(array|object|string|null $payload): static {
		$this -> entry["payload"] = $payload;
		return $this;
	}

	/**
	 * When it happened, for an entry recorded after the fact.
	 */
	public function at(int $created): static {
		$this -> entry["created"] = $created;
		return $this;
	}

	public function record(): Entry {
		if ($this -> recorded !== null)
			return $this -> recorded;

		$this -> recorded = Essence::make(Writer::class) -> write($this -> entry);
		return $this -> recorded;
	}
}
