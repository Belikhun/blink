<?php

use Blink\Terra\Layer;
use Blink\Terra\Schema;
use Blink\Terra\Table;

/**
 * API access tokens.
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
		Schema::create("tokens", function (Table $table) {
			$table -> id();
			$table -> string("token", 191) -> unique();
			$table -> string("username", 191) -> index();
			$table -> timestamp("created") -> default(0);
			$table -> timestamp("expire") -> index();
		});
	}

	public function drop(): void {
		Schema::dropIfExists("tokens");
	}
};
