<?php

namespace Blink;

use Blink\Essence\EssenceBinding;
use Blink\Essence\Exception\EssenceBindingNotDefined;
use Blink\Essence\Exception\EssenceCallArgumentMismatch;
use Blink\Essence\Exception\EssenceCircularDependency;
use Blink\Essence\Exception\EssenceCallMissingParam;
use Blink\Essence\Exception\EssenceInvalidCallable;

/**
 * Blink's dependency container.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Essence {
	/**
	 * Store all bindings.
	 *
	 * @var array<EssenceBinding[]>
	 */
	protected static array $bindings = [];

	/**
	 * Store all concrete bindings.
	 *
	 * @var EssenceBinding[]
	 */
	protected static array $concreteBindings = [];

	/**
	 * Store all singleton instances.
	 *
	 * @var array<object>
	 */
	protected static array $singletons = [];

	/**
	 * Track constructor calls to detect recursive dependency cycles.
	 *
	 * @var array<string>
	 */
	protected static array $constructorStack = [];

	/**
	 * Bind a class or interface to a concrete implementation.
	 *
	 * @template	TA
	 * @template	TC
	 * @param		class-string<TA>							$abstract
	 * @param		class-string<TC>|callable|object|null		$concrete
	 * @return		EssenceBinding<TA, TC>
	 */
	public static function bind(string $abstract, $concrete = null): EssenceBinding {
		if (static::bound($abstract)) {
			$binding = static::getBinding($abstract);
			$binding -> give($concrete);
			$binding -> isSingleton = false;
			return $binding;
		}

		$binding = new EssenceBinding($abstract);
		$binding -> concrete = $concrete ?: $abstract;
		$binding -> isSingleton = false;
		static::register($binding);

		return $binding;
	}

	/**
	 * Bind a class or interface to a concrete implementation if a binding has not already been registered.
	 *
	 * @template	TA
	 * @template	TC
	 * @param		class-string<TA>							$abstract
	 * @param		class-string<TC>|callable|object|null		$concrete
	 * @return		EssenceBinding<TA, TC>
	 */
	public static function bindIf(string $abstract, $concrete = null): EssenceBinding {
		if (static::bound($abstract))
			return static::getBinding($abstract);

		return static::bind($abstract, $concrete);
	}

	/**
	 * Bind a class or interface to a singleton concrete implementation.
	 *
	 * @template	TA
	 * @template	TC
	 * @param		class-string<TA>							$abstract
	 * @param		class-string<TC>|callable|object|null		$concrete
	 * @return		EssenceBinding<TA, TC>
	 */
	public static function singleton(string $abstract, $concrete = null): EssenceBinding {
		if (static::bound($abstract)) {
			$binding = static::getBinding($abstract);
			$binding -> give($concrete);
			$binding -> isSingleton = true;
			return $binding;
		}

		$binding = new EssenceBinding($abstract);
		$binding -> concrete = $concrete ?: $abstract;
		$binding -> isSingleton = true;
		static::register($binding);

		return $binding;
	}

	/**
	 * Bind a class or interface to a singleton concrete implementation if a binding has not already been registered.
	 *
	 * @template	TA
	 * @template	TC
	 * @param		class-string<TA>							$abstract
	 * @param		class-string<TC>|callable|object|null		$concrete
	 * @return		EssenceBinding<TA, TC>
	 */
	public static function singletonIf(string $abstract, $concrete = null): EssenceBinding {
		if (static::bound($abstract))
			return static::getBinding($abstract);

		return static::singleton($abstract, $concrete);
	}

	/**
	 * Define a conditional binding for an abstract class or interface.
	 *
	 * @template	TA
	 * @param		class-string<TA>		$context
	 * @return		EssenceBinding<TA>
	 */
	public static function when(string $context): EssenceBinding {
		return new EssenceBinding(null, $context);
	}

	public static function register(EssenceBinding $binding): void {
		if (empty($binding -> abstract))
			throw new \InvalidArgumentException("Cannot add a binding without an abstract class or interface.");

		static::$bindings[$binding -> abstract][$binding -> context] = $binding;

		if (is_string($binding -> concrete))
			static::$concreteBindings[$binding -> concrete] = $binding;
	}

	/**
	 * Check if a binding exists for the given abstract class or interface.
	 *
	 * @param	string		$abstract
	 * @param	string		$context
	 * @return	bool
	 */
	public static function bound(string $abstract, string $context = "*"): bool {
		if ($context !== "*") {
			if (isset(static::$bindings[$abstract][$context]))
				return true;

			$context = "*";
		}

		if (isset(static::$bindings[$abstract][$context]))
			return true;

		if (isset(static::$concreteBindings[$abstract]))
			return true;

		return false;
	}

	/**
	 * Get the binding for the given abstract class or interface.
	 *
	 * @param	string				$abstract
	 * @param	string				$context
	 * @return	?EssenceBinding
	 */
	public static function getBinding(string $abstract, string $context = "*"): ?EssenceBinding {
		if ($context !== "*") {
			if (isset(static::$bindings[$abstract][$context]))
				return static::$bindings[$abstract][$context];

			$context = "*";
		}

		if (isset(static::$bindings[$abstract][$context]))
			return static::$bindings[$abstract][$context];

		if (isset(static::$concreteBindings[$abstract]))
			return static::$concreteBindings[$abstract];

		return null;
	}

	/**
	 * Make an instance of the given abstract class or interface.
	 *
	 * @template	T
	 * @param		class-string<T>		$class
	 * @param		string				$context
	 * @return		T
	 */
	public static function make(string $class, string $context = "*") {
		if (static::bound($class, $context))
			return static::getBinding($class, $context) ?-> make();

		if (interface_exists($class))
			throw new EssenceBindingNotDefined($class);

		// No binding defined, attempt to create instance directly.
		return static::call([$class, "__construct"]);
	}

	public static function call(
		array|string|object|callable $callable,
		array $args = [],
		array $binds = []
	) {
		$constructorClass = null;

		if (!is_callable($callable)) {
			if (is_string($callable) && str_contains($callable, "::")) {
				$parts = explode("::", $callable);
				$callable = [$parts[0], $parts[1]];
			} else if (is_array($callable) && $callable[1] === "__construct") {
				$constructorClass = is_object($callable[0])
					? get_class($callable[0])
					: $callable[0];

				if (!empty(static::$singletons[$constructorClass]))
					return static::$singletons[$constructorClass];

				$cycleStart = array_search($constructorClass, static::$constructorStack, true);
				if ($cycleStart !== false) {
					$cycle = array_slice(static::$constructorStack, $cycleStart);
					$cycle[] = $constructorClass;
					throw new EssenceCircularDependency($cycle);
				}

				static::$constructorStack[] = $constructorClass;

				$callable = [$callable[0], $callable[1]];
			} else {
				throw new EssenceInvalidCallable($callable);
			}
		}

		// The constructor was pushed onto $constructorStack just above, so
		// everything from here on has to sit inside the try: reflection,
		// parameter resolution (which recurses back through make()) and the
		// instantiation itself. The finally is what pops it again. Without
		// that, an exception raised while resolving a constructor parameter
		// leaves the entry on a static stack for the rest of the process, and
		// the next unrelated make() reports a circular dependency that never
		// happened, hiding the failure that actually did.
		try {
			if (is_array($callable)) {
				$class = new \ReflectionClass($callable[0]);

				if ($class -> hasMethod($callable[1])) {
					$info = $class -> getMethod($callable[1]);
				} else {
					if ($callable[1] !== "__construct")
						throw new EssenceInvalidCallable($callable);

					$info = null;
				}

				$context = is_object($callable[0])
					? get_class($callable[0])
					: $callable[0];
			} else {
				$context = stringify($callable);
				$info = (is_string($callable) && str_contains($callable, "::"))
					? new \ReflectionMethod($callable)
					: new \ReflectionFunction($callable);
			}

			$callArgs = array();
			$params = $info ?-> getParameters() ?: [];
			$unnamedParamIndex = 0;

			foreach ($params as $param) {
				$type = $param -> getType();
				$name = $param -> getName();

				if ($type && $type instanceof \ReflectionNamedType) {
					$typeName = $type -> getName();
					$isPrimitive = in_array($typeName, ["int", "float", "string", "bool", "array", "callable", "iterable", "object", "mixed", "null"]);

					if (isset($binds[$typeName])) {
						$callArgs[] = $binds[$typeName];

						// Consume the positional argument that contributed this bind,
						// so subsequent positional lookups don't reuse it.
						foreach ($args as $k => $v) {
							if (is_int($k) && $v === $binds[$typeName]) {
								unset($args[$k]);

								if ($k === $unnamedParamIndex)
									$unnamedParamIndex++;

								break;
							}
						}

						continue;
					}

					if (!$isPrimitive && static::bound($typeName, $context)) {
						try {
							$callArgs[] = static::make($typeName, $context);
							continue;
						} catch (EssenceCallMissingParam|EssenceCallArgumentMismatch $e) {
							// The bound class is not directly instantiable. Fall through
							// to the default value or an explicitly supplied argument.
						}
					} else if (static::bound("\${$name}", $context)) {
						$callArgs[] = static::make("\${$name}", $context);
						continue;
					} else {
						// Only reach for the default when nothing was supplied for this
						// parameter; the named and positional resolution below has to
						// get the value otherwise. Defaulting unconditionally here would
						// throw away route parameters bound to optional primitives, such
						// as `?string $hash = null`.
						if ($param -> isOptional() && !isset($args[$name])) {
							$callArgs[] = $param -> getDefaultValue();
							continue;
						}
					}
				}

				if ($param -> isVariadic()) {
					// Merge left over args and end processing.
					$callArgs = array_merge($callArgs, $args);
					break;
				}

				if (isset($args[$name])) {
					if ($param -> isPassedByReference()) {
						// Explicitly pass by reference
						$callArgs[] = &$args[$name];
					} else {
						$callArgs[] = $args[$name];
					}

					unset($args[$name]);
					continue;
				} else {
					if ($param -> isOptional()) {
						$callArgs[] = $param -> getDefaultValue();
						continue;
					}
				}

				if (!isset($args[$unnamedParamIndex]))
					throw new EssenceCallMissingParam($callable, $name);

				if ($param -> isPassedByReference()) {
					// Explicitly pass by reference
					$callArgs[] = &$args[$unnamedParamIndex];
				} else {
					$callArgs[] = $args[$unnamedParamIndex];
				}

				unset($args[$unnamedParamIndex]);
				$unnamedParamIndex++;
			}

			if (is_array($callable) && $callable[1] === "__construct") {
				$instance = new $callable[0](...$callArgs);
				static::$singletons[$constructorClass] = $instance;

				return $instance;
			}

			return $callable(...$callArgs);
		} catch (\TypeError $e) {
			$message = $e -> getMessage();
			$traces = $e -> getTrace();
			$matches = array();

			// This catch also fires for a TypeError raised somewhere inside the
			// callable, which is not ours to translate. Only the one raised by
			// the call itself has `call_user_func_array` right below it.
			if (($traces[1]["function"] ?? null) === "call_user_func_array") {
				if (preg_match("/(Argument (\d+) passed to .+) must be/m", $message, $matches)) {
					$key = array_keys($args)[((int) $matches[2]) - 1];
					$message = str_replace($matches[1], "Value of \"$key\"", $message);

					throw new EssenceCallArgumentMismatch($message);
				}
			}

			throw $e;
		} catch (\ArgumentCountError $e) {
			throw new EssenceCallArgumentMismatch($e -> getMessage());
		} finally {
			if ($constructorClass !== null)
				array_pop(static::$constructorStack);
		}
	}

	public static function forgetSingleton(string $class): void {
		unset(static::$singletons[$class]);
	}

	/**
	 * Discard any in-flight constructor resolution state.
	 *
	 * A fatal raised mid-construction (a timeout, a memory limit) ends the
	 * process without unwinding the try/finally that pops the stack, so stale
	 * entries survive into the shutdown handler. Clearing them first is what
	 * keeps the shutdown flush from reporting a circular dependency in place
	 * of the fatal that actually happened.
	 */
	public static function clearConstructorStack(): void {
		static::$constructorStack = [];
	}
}
