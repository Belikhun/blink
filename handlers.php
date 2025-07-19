<?php
/**
 * Register system handlers.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Handlers;

use Blink\Exception\BaseException;
use Blink\Exception\ClassNotFound;
use Blink\Exception\FatalError;
use Blink\Exception\RuntimeError;
use function Blink\stop;

global $ERROR_STACK;

/** @var \Throwable[] */
$ERROR_STACK = array();

function ErrorHandler(int $code, string $text, string $file, int $line) {
	// Disable all middleware handing entirely.
	\Blink\Middleware::disable();

	// Diacard all output buffer to avoid garbage html.
	while (ob_get_level())
		ob_end_clean();
	
	$error = new RuntimeError(1000 + $code, $text);
	$error -> file = $file;
	$error -> line = $line;

	stop($error -> code, $error -> description, 500, $error);
}

function ExceptionHandler(\Throwable $e) {
	// Disable all middleware handing entirely.
	\Blink\Middleware::disable();

	// Discard all output buffer to avoid garbage html.
	while (ob_get_level())
		ob_end_clean();

	if ($e instanceof BaseException)
		stop($e -> code, $e -> description, $e -> status, $e);
	
		
	// Little hack to check if we got class not found error, translate
	// it to a proper class.
	$error = $e;
	$matches = null;
	$cre = '/Class \"(.+)\" not found/';
	if (preg_match($cre, $e -> getMessage(), $matches)) {
		$error = new ClassNotFound($matches[1]);
		$error -> applyFrom($e);
	}

	stop(1000 + $error -> getCode(), $error -> getMessage(), 500, $error);
}

function ShutdownHandler() {
	$error = error_get_last();

	if (!empty($error)) {
		// Disable all middleware handing entirely.
		\Blink\Middleware::disable();

		// Diacard all output buffer to avoid garbage html.
		while (ob_get_level())
			ob_end_clean();
		
		$fatal = new FatalError($error["type"], $error["message"]);
		$fatal -> file = $error["file"];
		$fatal -> line = $error["line"];

		stop($error["type"], $error["message"], 500, $fatal);
	}
}

set_exception_handler("Blink\\Handlers\\ExceptionHandler");
set_error_handler("Blink\\Handlers\\ErrorHandler", E_ALL);
register_shutdown_function("Blink\\Handlers\\ShutdownHandler");
