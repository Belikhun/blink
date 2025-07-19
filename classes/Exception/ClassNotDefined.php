<?php

namespace Blink\Exception;

/**
 * Exception when file has been loaded, but the class with that file haven't been defined.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ClassNotDefined extends BaseException {
	/**
	 * Target class name that does not exist after
	 * autoloading target file.
	 * @var string
	 */
	public string $class;

	public function __construct(string $class, string $file) {
		$this -> class = $class;

		parent::__construct(
			AUTOLOAD_CLASS_NOTDEFINED,
			"File for class <code>{$class}</code> loaded, but the file itself haven't defined the class.",
			500,
			array(
				"class" => $class,
				"file" => $file
			)
		);
	}
}
