<?php

namespace Blink;

use Blink\Http\Router;

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
}
