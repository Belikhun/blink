<?php

namespace Blink\Terra\Exception;

use Blink\Exception\BaseException;

/**
 * The schema change a layer asked for cannot be expressed on this engine.
 *
 * Raised while compiling rather than while executing, so a layer that would
 * only work on one engine fails before it has changed anything.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraUnsupported extends BaseException {
	public function __construct(string $what, string $instead = null) {
		parent::__construct(
			TERRA_UNSUPPORTED,
			"Terra cannot do this on the current database: {$what}",
			500,
			array( "what" => $what, "instead" => $instead ),
			$instead
		);
	}
}
