<?php
/**
 * ContextRenderer.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\ErrorPage;
use HTMLBuilder;

class ContextRenderer {
	public static function list(Array $data): String {
		if (empty($data))
			return HTMLBuilder::build("pre", content: "[empty]");

		$output = HTMLBuilder::startDIV([ "class" => "error-context-list" ]);
		$seq = isSequential($data);

		foreach ($data as $name => $value) {
			$row = (!$seq)
				? HTMLBuilder::span([ "class" => "name", "title" => strip_tags($name) ], $name)
				: "";
			
			$attrs = Array( "class" => [] );

			if (is_bool($value) || in_array($value, [ "true", "false", "1", "0" ])) {
				$value = cleanParam($value, TYPE_BOOL);
				$attrs["class"][] = "bool";
				$attrs["class"][] = $value ? "true" : "false";
				$value = Renderer::icon($value ? "check" : "cross") . ($value ? "true" : "false");
			} else {
				if (is_object($value)) {
					if (method_exists($value, "__toString"))
						$value = (String) $value;
					else
						$value = get_class($value);
				} else if ($value === null) {
					$value = "[NULL]";
				}

				$attrs["copyable"] = $value;
				$value = htmlspecialchars($value);
			}
			
			$row .= HTMLBuilder::build("pre", $attrs, $value);
			$output .= HTMLBuilder::div([ "class" => "row" ], $row);
		}

		$output .= HTMLBuilder::endDIV();
		return $output;
	}

	public static function body(Array $data): String {
		$content = $data["content"];
		$type = $data["type"];

		switch ($type) {
			case "application/json":
				$content = safeJSONParsing($content);
				$content = json_encode($content, JSON_PRETTY_PRINT);
				break;
			
			case "multipart/form-data":
			case "application/x-www-form-urlencoded":
				return static::list($data["form"]);

			default:
				if (empty($content))
					$content = "[empty]";
				break;
		}

		return HTMLBuilder::build("pre", [ "copyable" => $content ], htmlspecialchars($content));
	}

	public static function string(String $content) {
		return HTMLBuilder::build("pre", [], htmlspecialchars($content));
	}
}
