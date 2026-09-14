<?php

namespace Blink\Ether;

use Blink\Http\Request;

/**
 * Shared base context for router lifecycle middleware.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouterBaseContext {
	/**
	 * Whether the request was authenticated through the Authorization header.
	 *
	 * @var	bool
	 */
	public bool $authenticatedByHeader = false;

	/**
	 * Whether the request was made in cross-site mode.
	 *
	 * @var	bool
	 */
	public bool $isCrossSite = false;

	/**
	 * Whether the request is an OPTIONS preflight.
	 *
	 * @var	bool
	 */
	public bool $isOptionRequest = false;

	public function __construct(
		public Request $request
	) {}
}
