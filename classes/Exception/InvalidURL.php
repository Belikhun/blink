<?php

namespace Blink\Exception;

/**
 * InvalidURL.php
 * 
 * Invalid URL.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidURL extends BaseException {
	public function __construct(String $url) {
		parent::__construct(INVALID_URL, "The URL <code>$url</code> is invalid!", 400);
	}
}
