<?php

namespace Blink\Exception;

/**
 * Interface not found exception
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class InterfaceNotFound extends BaseException {
	/**
	 * Target interface name that does not exist after
	 * autoloading target file.
	 *
	 * @var string
	 */
	public string $interface;

	public function __construct(string $interface) {
		$this -> interface = $interface;

		parent::__construct(
			AUTOLOAD_CLASS_MISSING,
			"Interface <code>{$interface}</code> not found!",
			500,
			array( "interface" => $interface )
		);
	}
}
