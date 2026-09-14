<?php

namespace Blink\Essence\Exception;

use Blink\Exception\BaseException;
use function Blink\stringify;

/**
 * Exception thrown when a required binding is missing in a callable.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EssenceCallMissingBind extends BaseException {
	public function __construct($callable, string $abstract, string $name) {
		$callable = stringify($callable);

		parent::__construct(
			ESSENCE_CALL_MISSING_BIND,
			"The required binding <code>{$abstract}</code> <code>{$name}</code> is missing for the callable <code>{$callable}</code>.",
			500,
			[ "callable" => $callable, "name" => $name, "abstract" => $abstract ]
		);
	}
}
