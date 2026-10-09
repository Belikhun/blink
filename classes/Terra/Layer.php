<?php

namespace Blink\Terra;

/**
 * One schema change, applied once and recorded.
 *
 * A layer lives in a file named `YYYY_MM_DD_HHMMSS_snake_name.php` returning
 * an anonymous class:
 *
 * ```php
 * return new class extends Layer {
 * 	public function raise(): void {
 * 		Schema::create("widgets", function (Table $table) {
 * 			$table -> id();
 * 			$table -> string("name");
 * 			$table -> timestamps();
 * 		});
 * 	}
 *
 * 	public function drop(): void {
 * 		Schema::dropIfExists("widgets");
 * 	}
 * };
 * ```
 *
 * The file name is the identifier, so blink and an application can both carry
 * a `create_users_table` without colliding.
 *
 * A layer runs on one database instance, the one `database()` names, and its
 * schema calls need no instance of their own. Work that reaches another
 * instance, such as moving a table's rows from the default to this one, is
 * done through `Schema::on()` and `Schema::copy()`:
 *
 * ```php
 * public function database(): ?string { return "archive"; }
 *
 * public function raise(): void {
 * 	Schema::create("logs", ...);                       // on the archive
 * 	Schema::copy("logs", from: null, to: "archive");   // the default's rows
 * 	Schema::on(null, fn () => Schema::drop("logs"));   // and the default's table
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
abstract class Layer {
	/**
	 * Apply the change.
	 */
	abstract public function raise(): void;

	/**
	 * Undo the change.
	 *
	 * Left empty when the change cannot be undone, which
	 * {@see Layer::reversible()} then reports rather than pretending a revert
	 * worked.
	 */
	public function drop(): void {}

	/**
	 * Name of the database instance this layer changes, or null for the
	 * default. The layer runs there and nowhere else. While the site has no
	 * instance of that name the layer waits, and the rest of the stack goes
	 * on without it; it runs once the instance is registered.
	 */
	public function database(): ?string {
		return null;
	}

	/**
	 * Whether this layer applies to the current site at all. A layer that only
	 * makes sense on one engine, or only when an optional table is present,
	 * answers false and is recorded as applied without running.
	 */
	public function shouldRun(): bool {
		return true;
	}

	/**
	 * Whether the layer implements `drop()`.
	 */
	public function reversible(): bool {
		$method = new \ReflectionMethod($this, "drop");
		return $method -> getDeclaringClass() -> getName() !== self::class;
	}
}
