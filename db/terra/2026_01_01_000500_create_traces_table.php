<?php

use Blink\Terra\Layer;
use Blink\Terra\Schema;
use Blink\Terra\Table;

/**
 * The audit log.
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
		Schema::create("traces", function (Table $table) {
			$table -> id();
			$table -> bigInteger("actor_id") -> unsigned() -> nullable();
			$table -> string("actor_ip", 45) -> nullable();
			$table -> string("component", 64);
			$table -> string("scope", 128) -> nullable();
			$table -> string("field", 32) -> nullable();
			$table -> tinyInteger("mode") -> unsigned() -> default(5);
			$table -> integer("level") -> unsigned() -> default(20);
			$table -> string("message", 255);
			$table -> string("source_class", 191) -> nullable();
			$table -> bigInteger("source_id") -> unsigned() -> nullable();
			$table -> string("target_class", 191) -> nullable();
			$table -> bigInteger("target_id") -> unsigned() -> nullable();
			$table -> text("payload") -> nullable();
			$table -> timestamp("created") -> default(0);

			// Every listing is newest first within some filter, so each
			// filter column pairs with `created`.
			$table -> index("created");
			$table -> index([ "component", "created" ]);
			$table -> index([ "scope", "created" ]);
			$table -> index([ "field", "created" ]);
			$table -> index([ "mode", "created" ]);
			$table -> index([ "level", "created" ]);
			$table -> index([ "actor_id", "created" ]);
			$table -> index([ "target_class", "target_id", "created" ]);
			$table -> index([ "source_class", "source_id", "created" ]);
		});
	}

	public function drop(): void {
		Schema::dropIfExists("traces");
	}
};
