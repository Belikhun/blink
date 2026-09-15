<?php

namespace Blink;

use Blink\Http\Router;
use Blink\Ticker\Orbit;

/**
 * A unit of application wiring.
 *
 * Everything an application adds to blink is declared by a module: bindings,
 * routes, middleware, commands and observers. List them in
 * `CONFIG::$MODULES` and blink boots them in that order.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class Module {
	/**
	 * Declare bindings. Runs on every module before any of them is attached,
	 * so a binding made here is visible to all of them.
	 *
	 * Only bind here. Resolving a binding at this point can reach a module
	 * that has not had its turn yet, and a query would run against a schema
	 * that `terra:apply` has not raised yet.
	 */
	public function define(): void {}

	/**
	 * Wire the module up: routes, middleware, listeners, anything that needs
	 * the bindings every module declared.
	 *
	 * This runs on every request and every console command, `terra:apply`
	 * included, so it must not query the database.
	 */
	public function attach(): void {}

	/**
	 * Release whatever `attach()` claimed. Runs from the shutdown handler, in
	 * reverse boot order, with the response already flushed to the client.
	 */
	public function detach(): void {}

	/**
	 * Declare work that comes round on a schedule.
	 *
	 * Called by `ticker:tick`, once a minute, so it must stay cheap: build the
	 * entries and return. Whether one is actually due is decided afterwards.
	 */
	public function orbits(Orbit $orbit): void {}

	/**
	 * Config classes this module ships.
	 *
	 * Registered before any module is defined, so a setting is readable from
	 * the first line of `define()` onwards. Each class is filled from the
	 * environment and has its path settings created as it is registered.
	 *
	 * @return	class-string[]
	 */
	public function config(): array {
		return array();
	}

	/**
	 * Console commands this module ships.
	 *
	 * @return	class-string<\Blink\Console\Command>[]
	 */
	public function commands(): array {
		return array();
	}

	/**
	 * Event observers this module ships.
	 *
	 * Declared rather than registered because the listener registry rebuilds
	 * itself from this list without re-running `attach()`.
	 *
	 * @return	class-string[]
	 */
	public function observers(): array {
		return array();
	}

	/**
	 * Load every route definition file in a directory.
	 */
	protected function routes(string $directory): void {
		Router::loadRoutesFrom($directory);
	}

	/**
	 * Add a directory of schema layers to the stack.
	 *
	 * Call it from `define()`: discovery happens before any module attaches,
	 * and a path registered later would be missed by the apply that has
	 * already run.
	 */
	protected function layers(string $directory): void {
		$directory = rtrim(str_replace("\\", "/", $directory), "/");

		if (!in_array($directory, \CONFIG::$TERRA_PATHS, true))
			\CONFIG::$TERRA_PATHS[] = $directory;
	}
}
