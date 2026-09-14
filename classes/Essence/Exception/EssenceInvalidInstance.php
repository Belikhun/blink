<?php

namespace Blink\Essence\Exception;

use Blink\Exception\BaseException;
use function Blink\stringify;

/**
 * Exception thrown when an invalid instance is provided to a binding.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EssenceInvalidInstance extends BaseException {
	public function __construct($instance, string|callable $concrete, string $abstract = null) {
		$instance = stringify($instance);
		$concrete = stringify($concrete);

		parent::__construct(
			ESSENCE_INVALID_INSTANCE,
			"The provided instance <code>{$instance}</code> is invalid for the concrete class or callable <code>{$concrete}</code>.",
			500,
			[ "concrete" => $concrete, "instance" => $instance, "abstract" => $abstract ],
			!empty($abstract)
				? "The instance must be of type or an instance of <code>{$abstract}</code>."
				: "Ensure that the instance is of the correct type or an instance of the concrete class or interface it is being bound to."
		);
	}
}
