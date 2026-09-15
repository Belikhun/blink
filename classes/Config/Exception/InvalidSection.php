<?php

namespace Blink\Config\Exception;

use Blink\Exception\BaseException;

/**
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidSection extends BaseException {
	public string $section;

	public function __construct(string $class, string $reason) {
		$this -> section = $class;

		parent::__construct(
			CONFIG_INVALID_SECTION,
			"<code>{$class}</code> cannot be registered as a config section: {$reason}.",
			500,
			array( "class" => $class, "reason" => $reason )
		);
	}
}
