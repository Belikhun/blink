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
