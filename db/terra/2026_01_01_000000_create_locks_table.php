<?php

use Blink\Terra\Layer;
use Blink\Terra\Schema;
use Blink\Terra\Table;

/**
 * Cross-process locks.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
return new class extends Layer {
	public function raise(): void {
		Schema::create("locks", function (Table $table) {
			$table -> id();

			// The unique index is the arbiter: two processes racing to claim
			// the same lock both insert, and exactly one insert survives.
			$table -> string("name", 191) -> unique();

			$table -> string("token", 64);
			$table -> string("owner", 255) -> default("");
			$table -> timestamp("acquired");
			$table -> timestamp("expires") -> index();
		});
	}

	public function drop(): void {
		Schema::dropIfExists("locks");
	}
};
