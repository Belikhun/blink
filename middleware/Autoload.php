<?php
/**
 * Autoload.php
 * 
 * Autoload middleware definition.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Middleware;
use Blink\Middleware;

class Autoload extends Middleware {
	/**
	 * Load class definition file by class name. If this function
	 * cannot include the mentioned class, return false. Autoload
	 * will automatically fallback to find the class in the include
	 * list from {@link CONFIG::$INCLUDES}.
	 * 
	 * @param	string	$class	Class name need to be included now.
	 * @return	bool	Return true if the class have been loaded
	 * 					successfully, false otherwise.
	 */
	public static function load(String $class): bool {
		
		return false;
	}
}
