<?php

namespace Blink\Exception;

/**
 * A middleware reference could not be registered or resolved.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class EtherInvalidMiddleware extends BaseException {
	public string $reference;

	public function __construct(string $reference, string $details = null) {
		$this -> reference = $reference;

		parent::__construct(
			ETHER_INVALID_MIDDLEWARE,
			"Middleware reference <code>{$reference}</code> is invalid.",
			500,
			array( "reference" => $reference ),
			$details
		);
	}
}
