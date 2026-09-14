<?php

namespace Blink\Essence\Exception;

use Blink\Exception\BaseException;
use function Blink\stringify;

/**
 * Exception thrown when an invalid callable is provided.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EssenceInvalidCallable extends BaseException {
	public function __construct($callable) {
		$class = $callable;
		$method = null;

		if (is_array($callable))
			list($class, $method) = $callable;

		$isObject = is_object($class);
		$class = $isObject ? get_class($class) : $class;

		$callable = stringify($callable);

		parent::__construct(
			ESSENCE_INVALID_CALLABLE,
			"The provided callable <code>{$callable}</code> is invalid or not callable.",
			500,
			[ "callable" => $callable, "class" => $class, "method" => $method ],
			!empty($method)
				? "Callable <code>" . ($isObject ? "{$class} -> {$method}()" : "{$class}::{$method}()") . "</code> does not exist or is invalid."
				: "Function or method <code>{$class}</code> is invalid, does not exist or is not callable."
		);
	}
}
