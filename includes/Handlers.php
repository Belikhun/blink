<?php

/**
 * Handlers.php
 * 
 * Interface for core handlers. Built in handlers in this class
 * can be easily extender or overrided by applicaton.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;
use Blink\ErrorPage\Renderer;

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
			case \Blink\Exception\ClassNotDefined::class:
			case \Blink\Middleware\Exception\ClassNotDefined::class: {
				$className = $data["class"];
				$file = getRelativePath($data["file"]);

				$title = "Class không tồn tại";
				$content = "Có vẻ như bạn chưa định nghĩa class <b>{$className}</b> trong tệp <b>{$file}</b>.
							Kiểm tra lại chính tả hoặc thêm class này nếu bạn chưa định nghĩa nó.";

				$content .= Renderer::button(
					"Mở File",
					link: "vscode://file/" . urlencode($data["file"]),
					icon: "file-pen"
				);
				break;
			}

			case \Blink\Exception\RouteNotFound::class: {
				$title = "Đường dẫn không tồn tại";
				$content = "Có vẻ như đường dẫn này không còn tồn tại trên máy chủ. Liên hệ quản trị viên nếu bạn thấy đây là lỗi.";
				break;
			}

			case \Blink\Exception\InvalidDefinition::class:
			case \Blink\Middleware\Exception\InvalidDefinition::class: {
				$title = "Định nghĩa class không hợp lệ";
				$content = "Hãy đảm bảo rằng class <b>{$data['class']}</b> của bạn đã kế thừa <b>{$data['from']}</b>";

				if (!empty($data["file"])) {
					$content .= Renderer::button(
						"Mở File",
						link: "vscode://file/" . urlencode($data["file"]),
						icon: "file-pen"
					);
				}
				break;
			}
		}

		return Array( $title, $content );
	}

	/**
	 * Add additional contexts to error page. It will be shown
	 * in the bottom of the page.
	 * 
	 * @param	\Blink\ErrorPage\Instance		$instance
	 * @return	\Blink\ErrorPage\ContextGroup[]
	 */
	public static function errorContexts(\Blink\ErrorPage\Instance $instance): Array {
		return Array();
	}
}
