<?php

/**
 * Handler.php
 * 
 * Interface for core handlers. Built in handlers in this class
 * can be easily extender or overrided by applicaton.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;

class Handler {
	/**
	 * Give debug/fixing hint for user when they are faced with
	 * an error page. Can be used in built-in, or custom error
	 * page.
	 * 
	 * @param	string	$class
	 * @return	array	Consist of two items: [title, content]
	 * 					where title will be display highlighted
	 * 					and content will be displayed under the title.
	 * 					content can use HTML.
	 */
	public static function errorPageHint(String $class): Array {
		return Array( null, null );
	}
}
