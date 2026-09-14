<?php

namespace Blink\Exception;

/**
 * A module listed in `CONFIG::$MODULES` could not be loaded.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ModuleNotFound extends BaseException {
	public string $module;

	public function __construct(string $module) {
		$this -> module = $module;
		parent::__construct(
			MODULE_NOT_FOUND,
			"Module \"{$module}\" does not exist.",
			500,
			array( "module" => $module )
		);
	}
}
