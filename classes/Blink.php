<?php
/**
 * Blink's global public API.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

use Blink\Essence;
use Blink\Ether\Registry\MiddlewareRegistry;
use Blink\Ether\Registry\MiddlewareRegistryCache;
use Blink\Exception\CodingError;
use Blink\Lightrail\Dispatcher;
use Blink\Lightrail\EventCoordinator;
use Blink\Lightrail\Registry\ListenerRegistry;
use Blink\Lightrail\Registry\RegistryCache;
use Blink\Lightrail\Service\Recorder;
use Blink\Trace\LevelRegistry;
use Blink\Trace\ModeRegistry;
use Blink\Trace\Writer as TraceWriter;
use Blink\Exception\InvalidDefinition;
use Blink\Exception\ModuleNotFound;
use Blink\Model\UserBase;
use Blink\Module;
use Blink\Module\CoreModule;

final class Blink {
	/**
	 * Blink's own root directory.
	 */
	const CORE = CORE_ROOT;

	/**
	 * The application's root directory. Alias of {@see BASE_PATH}.
	 */
	const APP = BASE_PATH;

	/**
	 * Directory blink writes everything generated into.
	 */
	const DATA = DATA_ROOT;

	/**
	 * Booted modules, in boot order, mapped by class name.
	 *
	 * @var array<class-string<Module>, Module>
	 */
	protected static array $modules = array();

	/**
	 * The module and lifecycle phase that threw, for the error page to show.
	 *
	 * @var ?array{class: string, phase: string}
	 */
	public static ?array $failedModule = null;

	/**
	 * @var ?class-string<UserBase>
	 */
	protected static ?string $userClass = null;

	protected static ?string $cacheVersion = null;

	/**
	 * Return the blink version this application runs on.
	 */
	public static function version(): string {
		return \CONFIG::$BLINK_VERSION;
	}

	/**
	 * Return the version the application declares for itself.
	 */
	public static function appVersion(): string {
		return \CONFIG::$APP_VERSION;
	}

	public static function debuggingEnabled(): bool {
		return class_exists("CONFIG") && \CONFIG::$DEBUG;
	}

	/**
	 * Bind the services blink resolves for itself.
	 *
	 * Kept apart from module loading so the shutdown handler can reach them
	 * after a failure that stopped a module partway through.
	 */
	public static function registerCoreSingletons(): void {
		Essence::singletonIf(MiddlewareRegistryCache::class);
		Essence::singletonIf(MiddlewareRegistry::class);

		Essence::singletonIf(RegistryCache::class);
		Essence::singletonIf(ListenerRegistry::class);
		Essence::singletonIf(Recorder::class);
		Essence::singletonIf(Dispatcher::class);
		Essence::singletonIf(EventCoordinator::class);
		Essence::singletonIf(LevelRegistry::class);
		Essence::singletonIf(ModeRegistry::class);
		Essence::singletonIf(TraceWriter::class);
	}

	/**
	 * Boot every module the application declares, blink's own first.
	 *
	 * Runs in two passes: `define()` on all of them, then `attach()` on all of
	 * them. A module can therefore wire against a binding made by a module
	 * declared after it, which is the whole point of keeping the two apart.
	 */
	public static function loadModules(): void {
		$classes = array_merge(array( CoreModule::class ), \CONFIG::$MODULES);
		$modules = array();

		// Validate the whole list before anything runs, so a typo in the last
		// module is reported before the first one has wired anything up.
		foreach ($classes as $class) {
			$class = ltrim($class, "\\");

			if (!class_exists($class))
				throw new ModuleNotFound($class);

			if (!is_subclass_of($class, Module::class))
				throw new InvalidDefinition($class, Module::class);

			$modules[$class] = new $class();
		}

		static::$modules = $modules;

		static::runPhase("define", function (Module $module) {
			$module -> define();
		});

		// Through Essence so a module can type-hint the services it wires
		// against, rather than resolving each one by hand.
		static::runPhase("attach", function (Module $module) {
			Essence::call([ $module, "attach" ]);
		});
	}

	/**
	 * Tear down every booted module, in reverse boot order.
	 *
	 * Called from the shutdown handler, where a module that throws must not
	 * stop the rest from getting their turn.
	 */
	public static function detachModules(): void {
		foreach (array_reverse(static::$modules, true) as $class => $module) {
			try {
				$module -> detach();
			} catch (\Throwable $e) {
				error_log("[blink][shutdown] {$class} -> detach() failed: {$e}");
			}
		}
	}

	/**
	 * @return array<class-string<Module>, Module>
	 */
	public static function modules(): array {
		return static::$modules;
	}

	/**
	 * @template T of Module
	 * @param	class-string<T>		$class
	 * @return	?T
	 */
	public static function module(string $class): ?Module {
		return static::$modules[ltrim($class, "\\")] ?? null;
	}

	/**
	 * Version string every generated cache is keyed on, so blink, application
	 * and module changes invalidate them together.
	 *
	 * Only values readable straight from config go into it. The autoload cache
	 * is validated on the very first class load, long before any module has
	 * been instantiated, so nothing that needs a module instance can be part
	 * of this.
	 */
	public static function cacheVersion(): string {
		if (static::$cacheVersion !== null)
			return static::$cacheVersion;

		static::$cacheVersion = sha1(implode("\n", array_merge(
			array( static::version(), static::appVersion() ),
			\CONFIG::$MODULES
		)));

		return static::$cacheVersion;
	}

	/**
	 * Name the class blink resolves authenticated users into. Call it from a
	 * module's `define()`.
	 *
	 * @param	class-string<UserBase>	$class
	 */
	public static function useUserClass(string $class): void {
		$class = ltrim($class, "\\");

		if (!is_subclass_of($class, UserBase::class))
			throw new InvalidDefinition($class, UserBase::class);

		Essence::bind(UserBase::class, $class);
		static::$userClass = $class;
	}

	/**
	 * Return the user class the application registered.
	 *
	 * Read off the Essence binding rather than a static of its own, so an
	 * application that binds {@see UserBase} directly gets the same answer as
	 * one going through {@see Blink::useUserClass()}.
	 *
	 * @return	class-string<UserBase>
	 */
	public static function userClass(): string {
		if (!empty(static::$userClass))
			return static::$userClass;

		$concrete = Essence::getBinding(UserBase::class) ?-> concrete;

		if (empty($concrete) || !is_string($concrete)) {
			throw new CodingError(
				"Blink::userClass(): no user class has been registered.",
				"Create a class extending <code>\\Blink\\Model\\UserBase</code> and register it with "
					. "<code>Blink::useUserClass()</code> from your module's <code>define()</code>."
			);
		}

		static::$userClass = $concrete;
		return static::$userClass;
	}

	/**
	 * Render a placeholder avatar for the given name, as an inline SVG.
	 */
	public static function SvgAvatar(string $name, int $size = 200): string {
		$color = array(
			"A" => "#5A876F", "B" => "#B2B7BB", "C" => "#6FA9AB", "D" => "#F5AF29", "E" => "#0088B9", "F" => "#F18536",
			"G" => "#D93A37", "H" => "#B3BC50", "I" => "#5B9BBD", "J" => "#F5878C", "K" => "#9B89B5", "L" => "#407887",
			"M" => "#9B89B5", "N" => "#5A876F", "O" => "#D33F33", "P" => "#D33F33", "Q" => "#F1B126", "R" => "#0087BF",
			"S" => "#F18536", "T" => "#0087BF", "U" => "#B2B7BB", "V" => "#72ACAE", "W" => "#9B89B5", "X" => "#5A876F",
			"Y" => "#EEB424", "Z" => "#407887"
		);

		$letter = strtoupper($name[0]);

		ob_start(); ?>
		<svg width="<?php print $size; ?>" height="<?php print $size; ?>" xmlns="http://www.w3.org/2000/svg">
			<rect
				fill="<?php print $color[$letter] ?? "#846B32"; ?>"
				height="<?php print $size; ?>"
				width="<?php print $size; ?>"
			/>

			<text
				xml:space="preserve"
				text-anchor="middle"
				dominant-baseline="central"
				font-family="Nunito, Arial, sans-serif"
				font-size="<?php print ($size / 2) + 20; ?>"
				font-weight="bold"
				y="50%"
				x="50%"
				fill="#FFF"
			><?php print $letter; ?></text>
		</svg>
		<?php

		return ob_get_clean();
	}

	/**
	 * Run one lifecycle phase across every module, remembering which one threw.
	 */
	protected static function runPhase(string $phase, callable $handler): void {
		foreach (static::$modules as $class => $module) {
			try {
				$handler($module);
			} catch (\Throwable $e) {
				static::$failedModule = array( "class" => $class, "phase" => $phase );
				throw $e;
			}
		}
	}
}
