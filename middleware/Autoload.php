<?php
/**
 * Autoload.php
 * 
 * Autoload middleware definition.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Middleware;
use Blink\Exception\ClassNotDefined;
use Blink\Exception\InvalidDefinition;
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
		if ($class === "Handlers") {
			$default = CORE_ROOT . "/defaults/Handlers.php";
			$appPath = BASE_PATH . "/includes/Handlers.php";

			require_once CORE_ROOT . "/includes/Handlers.php";

			if (file_exists($appPath))
				require_once $appPath;
			else
				require_once $default;

			// Make sure the class have been included and defined correctly.
			if (!class_exists($class))
				throw new ClassNotDefined($class, $appPath);

			if (!in_array("Blink\\Handlers", class_parents($class, false)))
				throw new InvalidDefinition($class, "Blink\\Handlers");
		}

		return false;
	}
}
