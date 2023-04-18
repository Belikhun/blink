<?php
/**
 * ClassNotDefined.php
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;
use Blink\Exception\BaseException;

class ClassNotDefined extends BaseException {
	/**
	 * Target class name that is not defined after
	 * autoloading target file.
	 * @var string
	 */
	public String $class;

	/**
	 * Target file that loaded during autoload process that
	 * does not define specified class.
	 * @var string
	 */
	public String $targetFile;

	public function __construct(String $class, String $file) {
		$this -> class = $class;
		$this -> targetFile = getRelativePath($file);

		parent::__construct(
			AUTOLOAD_CLASS_MISSING,
			"Class [{$class}] is not defined in file \"{$this -> targetFile}\"!",
			500,
			Array( "class" => $class, "file" => $this -> targetFile )
		);
	}
}
