<?php

namespace Blink\Essence;

use Blink\Essence;
use Blink\Essence\Exception\EssenceInvalidConcrete;
use Blink\Essence\Exception\EssenceInvalidInstance;

/**
 * A binding from an abstract class or interface to a concrete class or
 * primitive value.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * @template	TA
 * @template	TC
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EssenceBinding {
	/**
	 * The abstract class or interface being bound.
	 *
	 * @var class-string<TA>
	 */
	public string $abstract;

	/**
	 * The concrete class or callable implementation.
	 *
	 * @var class-string<TC>|callable|object
	 */
	public $concrete;

	/**
	 * The context of this binding. Can be controller, service, etc.
	 *
	 * @var string
	 */
	public readonly string $context;

	/**
	 * Whether this binding is a singleton.
	 *
	 * @var bool
	 */
	public bool $isSingleton = false;

	/**
	 * The singleton instance, if applicable.
	 *
	 * @var ?TC
	 */
	protected $instance = null;

	protected bool $validated = false;

	/**
	 * @param	?class-string<TA>			$abstract
	 */
	public function __construct(?string $abstract, string $context = "*") {
		if (!empty($abstract))
			$this -> abstract = $abstract;

		$this -> context = $context;
	}

	/**
	 * Define the abstract class, interface or primitive this binding answers for.
	 *
	 * @param	class-string|string			$abstract
	 * @return	static<TA, TC>
	 */
	public function needs(string $abstract): EssenceBinding {
		$this -> abstract = $abstract;
		$this -> validated = false;
		$this -> validate();
		return $this;
	}

	/**
	 * Define the concrete class or primitive value for this binding.
	 *
	 * @param	class-string<TC>|callable|object	$instance
	 * @return	static<TA, TC>
	 */
	public function give(string|callable|object $instance): EssenceBinding {
		$this -> concrete = $instance;
		$this -> validated = false;
		$this -> validate();
		return $this;
	}

	public function singleton(bool $isSingleton = true) {
		$this -> isSingleton = $isSingleton;
		return $this;
	}

	/**
	 * @throws	EssenceInvalidConcrete
	 * @return	static<TA, TC>
	 */
	protected function validate() {
		if ($this -> validated)
			return $this;

		if (empty($this -> abstract) || empty($this -> concrete))
			return $this;

		if (!$this -> isPrimitive()) {
			if (is_string($this -> concrete) && !is_a($this -> concrete, $this -> abstract, true))
				throw new EssenceInvalidConcrete($this -> concrete, $this -> abstract);
		}

		Essence::register($this);
		$this -> validated = true;
		return $this;
	}

	/**
	 * Whether this binding answers for a named value rather than a class. The
	 * `$` prefix is what tells the two apart, matching how a parameter is
	 * written in the signature it gets injected into.
	 */
	public function isPrimitive(): bool {
		return str_starts_with($this -> abstract, "$");
	}

	public function isValidated(): bool {
		return $this -> validated;
	}

	/**
	 * Create an instance of the concrete class.
	 *
	 * @throws	EssenceInvalidInstance
	 * @return	?TC
	 */
	public function make() {
		$this -> validate();
		$isPrimitive = $this -> isPrimitive();

		if ($isPrimitive) {
			if (is_callable($this -> concrete))
				return call_user_func($this -> concrete);

			return $this -> concrete;
		}

		if ($this -> isSingleton && !empty($this -> instance))
			return $this -> instance;

		$instance = is_callable($this -> concrete)
			? call_user_func($this -> concrete)
			: $this -> concrete;

		if (is_string($instance)) {
			if (interface_exists($instance))
				throw new EssenceInvalidConcrete($instance, $this -> abstract);

			// Essence::call() caches every constructed instance so a cycle can be
			// detected. A binding that is not a singleton has to drop that cache
			// on both sides of the call, or the second make() hands back the first
			// instance.
			if (!$this -> isSingleton)
				Essence::forgetSingleton($instance);

			$instance = Essence::call([$instance, "__construct"]);

			if (!$this -> isSingleton)
				Essence::forgetSingleton($instance::class);
		}

		if (!is_a($instance, $this -> abstract)) {
			$this -> instance = null;

			throw new EssenceInvalidInstance(
				$instance,
				$this -> concrete,
				$this -> abstract
			);
		}

		$this -> instance = $instance;
		return $this -> instance;
	}
}
