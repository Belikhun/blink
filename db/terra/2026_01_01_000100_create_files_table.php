<?php

use Blink\Terra\Layer;
use Blink\Terra\Schema;
use Blink\Terra\Table;

/**
 * Stored files: one row per upload, one copy of the content.
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
		Schema::create("files", function (Table $table) {
			$table -> id();

			// Indexed rather than unique: each upload keeps its own name and
			// uploader, so several rows may point at the same content. The
			// store holds one copy, and drops it when the last row goes.
			// Long enough for sha512, should a site want it.
			$table -> string("hash", 128) -> index();

			$table -> string("filename", 255);
			$table -> string("extension", 32) -> default("");
			$table -> string("mimetype", 191) -> default("application/octet-stream");
			$table -> bigInteger("size") -> unsigned() -> default(0);

			// No foreign key: the users table belongs to the application, and
			// blink does not know its name.
			$table -> bigInteger("author_id") -> unsigned() -> nullable() -> index();

			$table -> timestamp("created") -> default(0);

			$table -> index("created");
		});
	}

	public function drop(): void {
		Schema::dropIfExists("files");
	}
};
