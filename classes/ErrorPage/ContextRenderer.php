<?php

namespace Blink\ErrorPage;

use Blink\HtmlWriter as H;
use Blink\Metric\Timing;

/**
 * ContextRenderer.php
 * 
 * Contain ultilities to render error page components.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ContextRenderer {
	public static function list(Array $data): String {
		if (empty($data))
			return H::build("pre", content: "[empty]");

		$output = H::startDIV([ "class" => "error-context-list" ]);
		$seq = isSequential($data);

		foreach ($data as $name => $value) {
			$row = (!$seq)
				? H::span([ "class" => "name", "title" => strip_tags($name) ], $name)
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
				} else if (is_array($value)) {
					$value = print_r($value, true);
				} else if ($value === null) {
					$value = "[NULL]";
				}

				$attrs["copyable"] = $value;
				$value = htmlspecialchars($value);
			}
			
			$row .= H::build("pre", $attrs, $value);
			$output .= H::div([ "class" => "row" ], $row);
		}

		$output .= H::endDIV();
		return $output;
	}

	public static function metricTiming(Array $data): String {
		global $RUNTIME;

		$start = $data["start"];
		$end = $data["end"];
		$duration = $end - $start;
		$labels = Array();
		$bars = Array();
		$lines = Array();

		/** @var Timing[] */
		$timings = $data["timings"];

		foreach ($timings as $timing) {
			$labels[] = H::build("div.label", content: $timing -> name);
			$color = "blue";

			$left = ($timing -> start - $start) / $duration;
			$right = ($timing -> time > 0)
				? ($timing -> time - $start) / $duration
				: 1;

			$t = ($timing -> getTime() > 0)
				? convertTime($timing -> getTime())
				: "fail";

			$lp = ($left * 100) . "%";
			$rp = ((1 - $right) * 100) . "%";

			if ($timing -> time <= 0)
				$color = "red";

			$bars[] = H::build(
				"div.bar",
				[ "data-color" => $color ],
				content: H::build(
					"div.inner",
					[ "style" => "left: $lp; right: $rp;" ],
					H::build("span", content: $t)
				)
			);
		}

		$lineSteps = array_reverse([0.005, 0.01, 0.05, 0.1, 0.5, 1, 2, 5, 10, 30, 60, 120, 240]);
		$lineStep = 0.005;
		$lineTime = 0;

		// Find suitable time step.
		foreach ($lineSteps as $step) {
			if ($duration / $step > 5) {
				$lineStep = $step;
				break;
			}
		}

		while ($lineTime < $duration) {
			$lp = (($lineTime / $duration) * 100) . "%";
			$lines[] = H::build(
				"div.line",
				[ "style" => "left: $lp" ],
				H::build("span", content: ($lineTime * 1000) . "ms"));
			
			$lineTime += $lineStep;
		}

		$output = H::start("div.timing-context");
		$output .= H::build("span.labels", content: implode("", $labels));
		$output .= H::start("span.right");
		$output .= H::build("div.lines", content: implode("", $lines));
		$output .= H::build("div.bars", content: implode("", $bars));
		$output .= H::end("span");
		$output .= H::end("div");

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

		return H::build("pre", [ "copyable" => $content ], htmlspecialchars($content));
	}

	public static function string(String $content) {
		return H::build("pre", [], htmlspecialchars($content));
	}
}
