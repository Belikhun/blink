<?php

namespace Blink\Essence\Exception;

use Blink\Exception\BaseException;
use function Blink\stringify;

/**
 * Exception thrown when an invalid concrete class or interface is provided to a binding.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EssenceInvalidConcrete extends BaseException {
	public function __construct(string|callable $concrete, string $abstract = null) {
		$concrete = stringify($concrete);

		$details = !empty($abstract)
			? "The concrete class or interface must implement or extend <code>{$abstract}</code>."
			: "Ensure that the concrete class or interface exists and implements or extends the abstract class or interface it is being bound to.";

		if (!empty($abstract) && strcasecmp($concrete, $abstract) === 0)
			$details = "The concrete class or interface cannot be the same as the abstract class or interface.";

		parent::__construct(
			ESSENCE_INVALID_CONCRETE,
			"The provided concrete class or callable <code>{$concrete}</code> is invalid.",
			500,
			[ "concrete" => $concrete, "abstract" => $abstract ],
			$details
		);
	}
}
