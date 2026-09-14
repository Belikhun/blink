<?php

namespace Blink\Essence\Exception;

use Blink\Exception\BaseException;

/**
 * Exception thrown when a binding is not defined for a given abstract class or interface.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EssenceBindingNotDefined extends BaseException {
	public function __construct(string $abstract) {
		parent::__construct(
			ESSENCE_BINDING_NOT_DEFINED,
			"No binding is defined for the abstract class or interface <code>{$abstract}</code>.",
			500,
			[ "abstract" => $abstract ]
		);
	}
}
