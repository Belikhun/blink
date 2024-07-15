<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

/**
 * ClassNotDefined.php
 * 
 * A class has not been defined in the included file.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ClassNotDefined extends BaseException {
	/**
	 * Target class name that is not defined after
	 * autoloading target file.
	 * @var string
	 */
	public string $class;

	/**
	 * Target file that loaded during autoload process that
	 * does not define specified class.
	 * @var string
	 */
	public string $targetFile;

	public function __construct(string $class, string $file) {
		$this -> class = $class;
		$this -> targetFile = getRelativePath($file);

		parent::__construct(
			AUTOLOAD_CLASS_MISSING,
			"Class [{$class}] is not defined in file \"{$this -> targetFile}\"!",
			500,
			array( "class" => $class, "file" => $this -> targetFile )
		);
	}
}
