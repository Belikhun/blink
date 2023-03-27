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

class Handlers {
	/**
	 * Give debug/fixing hint for user when they are faced with
	 * an error page. Can be used in built-in, or custom error
	 * page.
	 * 
	 * @param	string			$class
	 * @param	?array|?object	$data	Additional data of the exception class got from
	 * 									{@link \Blink\Exception\BaseException -> data}
	 * @return	array			Consist of two items: [title, content]
	 * 							where title will be display highlighted
	 * 							and content will be displayed under the title.
	 * 							content can use HTML.
	 */
	public static function errorPageHint(String $class, $data = null): Array {
		$title = null;
		$content = "";

		switch ($class) {
			case \Blink\Exception\ClassNotDefined::class: {
				$className = $data["class"];
				$file = $data["file"];

				$title = "Class không tồn tại";
				$content = "Có vẻ như bạn chưa định nghĩa class <code>{$className}</code> trong tệp <code>{$file}</code>.
							Kiểm tra lại chính tả hoặc thêm class này nếu bạn chưa định nghĩa nó.";
				break;
			}
		}

		return Array( $title, $content );
	}
}