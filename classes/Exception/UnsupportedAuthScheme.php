<?php

namespace Blink\Exception;

/**
 * Class representing an unsupported authorization scheme.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class UnsupportedAuthScheme extends BaseException {
	public function __construct(string $scheme) {
		parent::__construct(UNSUPPORTED_AUTH_SCHEME, "Authorization scheme <code>{$scheme}</code> is not supported!", 400);
	}
}
