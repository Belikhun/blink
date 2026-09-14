<?php

use Blink\Terra\Layer;
use Blink\Terra\Schema;
use Blink\Terra\Table;

/**
 * The dispatch trail and the batched event buffer.
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
		Schema::create("lightrail_dispatch_logs", function (Table $table) {
			$table -> id();

			// Ties the dispatch to its handler rows, and lets a batched insert
			// find the ids it just generated.
			$table -> string("correlation_id", 64) -> unique();

			$table -> string("event_class", 191) -> index();
			$table -> longText("event_payload") -> nullable();
			$table -> string("dispatch_mode", 32) -> default("immediate");
			$table -> string("status", 32) -> index();
			$table -> integer("total_handlers") -> unsigned() -> default(0);
			$table -> integer("pending_handlers") -> unsigned() -> default(0);
			$table -> integer("completed_handlers") -> unsigned() -> default(0);
			$table -> integer("success_handlers") -> unsigned() -> default(0);
			$table -> integer("error_handlers") -> unsigned() -> default(0);
			$table -> float("duration") -> default(0);
			$table -> timestamps();

			$table -> index("created");
		});

		Schema::create("lightrail_handler_logs", function (Table $table) {
			$table -> id();
			$table -> bigInteger("dispatch_id") -> unsigned() -> index();
			$table -> string("correlation_id", 64) -> index();
			$table -> string("event_class", 191);
			$table -> string("handler", 255);
			$table -> string("handler_key", 64) -> index();
			$table -> string("module", 191) -> nullable();
			$table -> tinyInteger("observer") -> unsigned() -> default(0);
			$table -> string("execution_mode", 32);
			$table -> string("status", 32);
			$table -> integer("attempt_number") -> unsigned() -> default(1);
			$table -> bigInteger("retry_of") -> unsigned() -> nullable();
			$table -> text("error_message") -> nullable();
			$table -> string("report_id", 64) -> nullable();
			$table -> string("trace_location", 255) -> nullable();
			$table -> float("duration") -> default(0);
			$table -> timestamps();

			// What a runner asks for: the pending handlers of one dispatch in
			// one execution mode.
			$table -> index([ "dispatch_id", "status", "execution_mode" ]);
		});

		Schema::create("lightrail_batched_events", function (Table $table) {
			$table -> id();
			$table -> string("handler_key", 64);
			$table -> string("batch_key", 191) -> default("");
			$table -> string("event_class", 191);
			$table -> longText("event_payload") -> nullable();

			// Empty while the row waits; set by the flush that claimed it.
			$table -> string("claim_token", 64) -> default("") -> index();

			$table -> bigInteger("dispatch_id") -> unsigned() -> default(0);
			$table -> timestamps();

			// How a flush finds its buffer: one handler, one batch key,
			// unclaimed.
			$table -> index([ "handler_key", "batch_key", "claim_token" ]);
		});
	}

	public function drop(): void {
		Schema::dropIfExists("lightrail_batched_events");
		Schema::dropIfExists("lightrail_handler_logs");
		Schema::dropIfExists("lightrail_dispatch_logs");
	}
};
