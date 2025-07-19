<?php

namespace Blink\Http\Response;

use Blink;
use Blink\Http\Response;
use Blink\URL;
use function Blink\getRelativePath;

/**
 * Return a redirect directive to the client.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RedirectResponse extends Response {
	public function __construct(string|URL $url) {
		parent::__construct();

		if ($url instanceof URL)
			$url = $url -> out(false);

		$redirectBy = "Blink";

		if (Blink::debuggingEnabled()) {
			$origin = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $redirectBy .= " (" . getRelativePath($origin["file"]) . ":" . $origin["line"] . ")";
		}

		$this -> status(303)
			-> header("Location", $url)
			-> header("X-Redirect-By", $redirectBy);
	}
}
