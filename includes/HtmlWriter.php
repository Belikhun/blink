<?php

/**
 * HtmlWriter.php
 * 
 * Simple HTML builder for rendering html code in
 * dev-friendly way.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class HtmlWriter {
	const SELF_CLOSING = Array("area", "base", "basefont", "br", "hr", "input", "img", "link", "meta");

	protected static function parse(String $target) {
		$tag = null;
		$id = null;
		$classes = Array();
		$attributes = Array();

		$re = '/^([a-zA-Z0-9]+)/m';

		if (preg_match($re, $target, $tM)) {
			$tag = $tM[1];

			if (str_contains($tag, "#") || str_contains($tag, ".")) {
				// Parse ID and classes.
				$re = '/([.#])([a-zA-Z0-9\-\_]+)/m';
	
				if (preg_match_all($re, $target, $cM, PREG_SET_ORDER)) {
					foreach ($cM as $item) {
						if ($item[1] === ".")
							$classes[] = $item[2];
						else if ($item[1] === "#")
							$id = $item[2];
					}
				}
			}

			if (str_contains($tag, "[")) {
				// Parse attributes
				$re = '/\[([a-zA-Z0-9=\'\" ]+)\]/mU';
	
				if (preg_match_all($re, $target, $aM, PREG_SET_ORDER)) {
					foreach ($aM as $item) {
						$parts = explode("=", $item[1]);
						
						if (empty($parts[1])) {
							$attributes[$parts[0]] = true;
							continue;
						}
	
						$attributes[$parts[0]] = trim($parts[1], "\"'");
					}
				}
			}
		}

		return Array( $tag, $id, $classes, $attributes );
	}

	/**
	 * Build the HTML string tag and return it.
	 *
	 * @param	string		$tag			Tag name. Accept CSS expression.
	 * @param	array		$attributes
	 * @param	string		$content
	 * @param	bool		$end			Append end tag
	 * @return	string
	 * 
	 * @version	1.0
	 * @author	Belikhun <belivipro9x99@gmail.com>
	 */
	public static function build(String $tag, Array $attributes = Array(), String $content = "", bool $end = true) {
		if (str_contains($tag, "#") || str_contains($tag, ".") || str_contains($tag, "[")) {
			$parsed = static::parse($tag);

			if (!empty($parsed[0])) {
				list($tag, $id, $classes, $parsedAttrs) = $parsed;
				$parsedAttrs["id"] = $id;

				if (!empty($attributes["class"])) {
					if (is_array($attributes["class"]))
						$attributes["class"] = array_merge($attributes["class"], $classes);
					else
						$attributes["class"] .= " " . implode(" ", $classes);
				} else {
					$attributes["class"] = $classes;
				}

				$attributes = array_merge($parsedAttrs, $attributes);
			}
		}

		$html = "<$tag";
		$attrs = Array();

		foreach ($attributes as $attribute => $value) {
			if (is_array($value))
				$value = implode(" ", array_filter($value));

			if (!is_scalar($value))
				continue;

			if ($value === false || $value === null)
				continue;

			if ($value === true) {
				$attrs[] = $attribute;
			} else {
				$value = htmlspecialchars($value);
				$attrs[] = $attribute . "=\"$value\"";
			}
		}

		if (!empty($attrs))
			$html .= " " . implode(" ", $attrs);

		if (!in_array($tag, static::SELF_CLOSING)) {
			$html .= ">$content";
	
			if ($end)
				$html .= self::end($tag);
		} else {
			$html .= " />";
		}

		return $html;
	}

	/**
	 * Build the HTML string tag and return it.
	 * Alias of {@link HtmlWriter::build}
	 *
	 * @param	string		$tag			Tag name. Accept CSS expression.
	 * @param	array		$attributes
	 * @param	string		$content
	 * @param	bool		$end			Append end tag
	 * @return	string
	 * 
	 * @version	1.0
	 * @author	Belikhun <belivipro9x99@gmail.com>
	 */
	public static function tag(String $tag, String $content = "", Array $attributes = Array(), bool $end = true) {
		return static::build($tag, $attributes, $content, $end);
	}

	public static function end(String $tag) {
		return "</$tag>";
	}

	public static function div(Array $attributes = Array(), String $content = "") {
		return self::build("div", $attributes, $content);
	}

	public static function startDIV(Array $attributes = Array(), String $content = "") {
		return self::build("div", $attributes, $content, false);
	}

	public static function endDIV() {
		return "</div>";
	}

	public static function span(Array $attributes = Array(), String $content = "") {
		return self::build("span", $attributes, $content);
	}

	public static function startSPAN(Array $attributes = Array(), String $content = "") {
		return self::build("span", $attributes, $content, false);
	}

	public static function endSPAN() {
		return "</span>";
	}

	public static function code(Array $attributes = Array(), String $content = "") {
		return self::build("code", $attributes, $content);
	}

	public static function p(String $content = "", Array $attributes = Array()) {
		return self::build("p", $attributes, $content);
	}

	public static function a(String $href, String $title, Array $attributes = Array()) {
		if (!isset($attributes["title"]))
			$attributes["title"] = strip_tags($title);

		$attributes["href"] = $href;
		return self::build("a", $attributes, $title);
	}

	public static function img(String $src, Array $attributes = Array()) {
		$attributes["src"] = $src;
		return self::build("img", $attributes);
	}

	public static function css(String $src, Array $attributes = Array()) {
		$attributes = array_merge(Array(
			"rel" => "stylesheet",
			"href" => $src
		), $attributes);

		return self::build("link", $attributes);
	}
}