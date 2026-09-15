<?php

namespace Blink\Config\Exception;

use Blink\Config\Setting;
use Blink\Exception\BaseException;

/**
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InvalidSettingValue extends BaseException {
	public string $key;

	public function __construct(Setting $setting, string $value, string $expected) {
		$this -> key = $setting -> key;

		$shown = $setting -> secret
			? Setting::MASK
			: $value;

		$source = ($setting -> env !== null)
			? "Environment variable <code>{$setting -> env}</code> carries <code>{$shown}</code>."
			: "The value given was <code>{$shown}</code>.";

		parent::__construct(
			CONFIG_INVALID_VALUE,
			"Setting <code>{$setting -> key}</code> needs {$expected}.",
			500,
			array( "key" => $setting -> key, "expected" => $expected ),
			$source
		);
	}
}
