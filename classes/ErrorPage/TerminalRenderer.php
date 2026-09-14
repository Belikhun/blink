<?php

namespace Blink\ErrorPage;

use Blink\Console\Output;
use Blink\ErrorPage;

/**
 * Render a report as text, for a console that has no use for the HTML page.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerminalRenderer {
	public static function render(ErrorPage $page, ?Output $output = null): string {
		$output = $output ?? new Output(STDERR, STDERR);

		list($error, $description, $details) = $page -> info();
		$exception = $page -> exception();
		$lines = array();

		$lines[] = $output -> paint($error, "red") . ": " . static::text($description);

		if (!empty($exception["file"]))
			$lines[] = "  at {$exception["file"]}:{$exception["line"]}";

		if (!empty($details)) {
			foreach (static::wrap(static::text($details)) as $line)
				$lines[] = "  {$line}";
		}

		list($tipTitle, $tipContent) = $page -> tips();

		if (!empty($tipTitle)) {
			$lines[] = "";
			$lines[] = $output -> paint("Tip: " . static::text($tipTitle), "yellow");

			foreach (static::wrap(static::text($tipContent)) as $line)
				$lines[] = "  {$line}";
		}

		$frames = $page -> stacktrace();

		if (!empty($frames)) {
			$lines[] = "";
			$lines[] = "Stack trace:";

			foreach ($frames as $i => $frame) {
				$location = !empty($frame -> file)
					? "{$frame -> file}:{$frame -> line}"
					: "[internal]";

				if ($frame -> fault)
					$location = $output -> paint($location, "red");
				else if ($frame -> isBlink() || $frame -> isVendor())
					$location = $output -> paint($location, "gray");

				$lines[] = sprintf("%s #%-2d %s", $frame -> fault ? ">" : " ", $i, $location);
				$lines[] = "        " . $frame -> getCallString() . "()";
			}
		}

		$lines[] = "";
		$lines[] = $output -> paint("Report {$page -> id}", "gray");

		return implode(PHP_EOL, $lines) . PHP_EOL;
	}

	public static function print(ErrorPage $page): void {
		$stream = defined("STDERR") ? STDERR : fopen("php://stderr", "w");
		fwrite($stream, PHP_EOL . static::render($page));
	}

	/**
	 * Strip the markup the page renders, keeping the line breaks that lists
	 * and paragraphs implied.
	 */
	protected static function text(string $html): string {
		$html = preg_replace('/<\s*(br|\/p|\/li|\/ul|\/div)\s*\/?>/i', "\n", $html);
		$html = preg_replace('/<\s*li\s*>/i', " - ", $html);
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

		return trim(preg_replace('/\n{3,}/', "\n\n", $text));
	}

	/**
	 * @return	string[]
	 */
	protected static function wrap(string $text, int $width = 76): array {
		$lines = array();

		foreach (explode("\n", $text) as $paragraph)
			array_push($lines, ...explode("\n", wordwrap($paragraph, $width)));

		return $lines;
	}
}
