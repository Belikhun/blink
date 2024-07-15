<?php

namespace Blink;

use Blink\Exception\IllegalAccess;
use Blink\Exception\TemplateNotFound;
use Blink\Template\Functions;

/**
 * Template.php
 * 
 * Simple template interface.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

class Template {
	public static string $ROOT = "";

	protected static function path(string $name): string {
		$path = static::$ROOT . "/{$name}.php";
		$real = realpath($path);

		if (!$real)
			throw new TemplateNotFound($name);

		$path = str_replace("\\", "/", $real);

		// Included file is outside of web root. This indicate
		// an attempt at LFI attack.
		if (strpos($path, static::$ROOT) !== 0)
			throw new IllegalAccess();
		
		return $path;
	}

	public static function render(string $name, array $context = []): string {
		// In case we render another template inside another template,
		// we back up current context and apply new context to process.
		$prevContext = Functions::$context;
		Functions::$context = $context;

		$templatePath = static::path($name);
		$templateContext = $context;

		$content = (function () use ($templatePath, $templateContext) {
			// Isolate template variable scope.
			extract($templateContext);

			ob_start();
			require $templatePath;
			return ob_get_clean();
		})();

		Functions::$context = $prevContext;
		return static::process($content, $context);
	}

	protected static function process(string $content, array $context): string {
		return $content;
	}
}

Template::$ROOT = \CONFIG::$TEMPLATES_ROOT;
