<?php

namespace Blink\Module;

use Blink\ErrorPage\Instance;
use Blink\Http\Router;
use Blink\Module;

/**
 * Blink's own module. Always the first one booted.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CoreModule extends Module {
	public function attach(): void {
		Router::$processingFile = "blink:core";

		// Negative priority keeps these behind anything the application
		// registers on the same paths.
		Router::GET("/error/{id}", [ Instance::class, "handle" ], -1);
		Router::GET("/error", [ Instance::class, "index" ], -1);

		Router::$processingFile = null;
	}
}
