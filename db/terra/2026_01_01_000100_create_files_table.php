<?php

use Blink\Terra\Layer;
use Blink\Terra\Schema;
use Blink\Terra\Table;

/**
 * Uploaded files.
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
			$table -> string("hash", 64) -> unique();
			$table -> string("filename", 255);
			$table -> string("extension", 32);
			$table -> string("mimetype", 191);
			$table -> bigInteger("size") -> unsigned() -> default(0);

			// No foreign key: the users table belongs to the application, and
			// blink does not know its name.
			$table -> bigInteger("author") -> unsigned() -> nullable() -> index();

			$table -> timestamp("created") -> default(0);
		});
	}

	public function drop(): void {
		Schema::dropIfExists("files");
	}
};
