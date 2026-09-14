<?php

namespace Blink\Ether\Request;

use Blink\Ether\RouterBaseContext;
use Blink\Http\Request;
use Blink\Http\Router\Route;

/**
 * Request middleware execution context.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RequestContext extends RouterBaseContext {
	/**
	 * Result returned by downstream middleware or route callback.
	 *
	 * @var	mixed
	 */
	public mixed $result = null;

	public function __construct(
		Request $request,
		public Route $route
	) {
		parent::__construct($request);
	}
}
