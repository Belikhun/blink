<?php

namespace Blink\Exception;

/**
 * Exception thrown when client don't have permission to access requesting resources.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class PermissionDenied extends BaseException {
	public function __construct(string $detail) {
		parent::__construct(
			NO_PERMISSION,
			"Permission denied!",
			400,
			null,
			$detail
		);
	}
}
