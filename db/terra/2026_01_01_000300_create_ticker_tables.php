<?php

use Blink\Terra\Layer;
use Blink\Terra\Schema;
use Blink\Terra\Table;

/**
 * Queued tasks, the ones that gave up, and the recurring entries.
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
		Schema::create("ticker_tasks", function (Table $table) {
			$table -> id();
			$table -> string("queue", 64);
			$table -> longText("payload");

			// Not unique: a key coalesces only among tasks nobody has claimed,
			// and the store checks that itself. A unique index here would also
			// reject a second run of work that has legitimately come round
			// again.
			$table -> string("task_key", 191) -> nullable();

			$table -> integer("attempts") -> unsigned() -> default(0);
			$table -> timestamp("available") -> default(0);
			$table -> timestamp("reserved") -> default(0);
			$table -> timestamp("expires") -> default(0);
			$table -> string("token", 64) -> nullable();
			$table -> timestamp("created") -> default(0);

			// The claim reads by queue and eligibility, which is every
			// statement a runner makes in its hot loop.
			$table -> index([ "queue", "available" ]);
			$table -> index("task_key");
			$table -> index("expires");
		});

		Schema::create("ticker_failures", function (Table $table) {
			$table -> id();
			$table -> string("queue", 64);
			$table -> longText("payload");
			$table -> string("task_key", 191) -> nullable();
			$table -> string("label", 255) -> default("");
			$table -> integer("attempts") -> unsigned() -> default(0);
			$table -> text("error") -> nullable();
			$table -> string("location", 255) -> nullable();

			// The error page report holding the stack trace, when one could be
			// filed.
			$table -> string("report", 64) -> nullable();

			$table -> timestamp("failed") -> index();
		});

		Schema::create("ticker_orbits", function (Table $table) {
			$table -> id();

			// The unique index is what makes a minute claimable: several hosts
			// inserting the same entry at once leave exactly one winner.
			$table -> string("name", 191) -> unique();

			// The minute already claimed, as a count of minutes since the
			// epoch. Compared rather than overwritten, so a late tick cannot
			// reclaim a minute that has passed.
			$table -> bigInteger("last_minute") -> default(0);

			$table -> timestamp("last_run") -> default(0);
			$table -> string("last_outcome", 32) -> default("");
			$table -> text("last_error") -> nullable();
			$table -> string("last_report", 64) -> nullable();
			$table -> integer("failures") -> unsigned() -> default(0);
		});
	}

	public function drop(): void {
		Schema::dropIfExists("ticker_orbits");
		Schema::dropIfExists("ticker_failures");
		Schema::dropIfExists("ticker_tasks");
	}
};
