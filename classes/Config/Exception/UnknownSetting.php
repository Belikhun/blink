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
class UnknownSetting extends BaseException {
	public string $key;

	/**
	 * @param	string		$key
	 * @param	string[]	$suggestions	Keys close enough to be worth offering.
	 */
	public function __construct(string $key, array $suggestions = array()) {
		$this -> key = $key;

		$details = !empty($suggestions)
			? "Did you mean <code>" . implode("</code>, <code>", $suggestions) . "</code>?"
			: null;

		parent::__construct(
			CONFIG_SETTING_NOT_FOUND,
			"No setting is registered as <code>{$key}</code>.",
			500,
			array( "key" => $key, "suggestions" => $suggestions ),
			$details
		);
	}
}
