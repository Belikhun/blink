<?php

namespace Blink\Ether\Response;

use Blink\Ether\RouterBaseContext;
use Blink\Http\Request;
use Blink\Http\Response;
use Blink\Http\Router\Route;

/**
 * Response middleware execution context.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ResponseContext extends RouterBaseContext {
	public function __construct(
		Request $request,
		public Route $route,
		public Response $response
	) {
		parent::__construct($request);
	}
}
