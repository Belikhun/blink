<?php

namespace Blink\Exception;

/**
 * Class not found exception
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ClassNotFound extends BaseException {
	/**
	 * Target class name that does not exist after
	 * autoloading target file.
	 * @var string
	 */
	public string $class;

	public function __construct(string $class) {
		$this -> class = $class;

		parent::__construct(
			AUTOLOAD_CLASS_MISSING,
			"Class <code>{$class}</code> not found!",
			500,
			array( "class" => $class )
		);
	}
}
