<?php

namespace Blink\Exception;

/**
 * Exception used when an instance with a specific data already exists.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InstanceExists extends BaseException {
	public function __construct(string $details) {
		parent::__construct(INSTANCE_EXISTS, $details, 400);
	}
}
