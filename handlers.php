<?php
/**
 * Register system handlers.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Handlers;

use Blink\Exception\BaseException;
use Blink\Exception\ClassNotFound;
use Blink\Exception\FatalError;
use Blink\Exception\InterfaceNotFound;
use Blink\Exception\RuntimeError;
use function Blink\stop;

global $ERROR_STACK;

/** @var \Throwable[] */
$ERROR_STACK = array();

function ErrorHandler(int $code, string $text, string $file, int $line) {
	// Discard all output buffer to avoid garbage html.
	while (ob_get_level())
		ob_end_clean();

	$error = new RuntimeError(1000 + $code, $text);
	$error -> file = $file;
	$error -> line = $line;

	stop($error -> code, $error -> description, 500, $error);
}

function ExceptionHandler(\Throwable $e) {
	// Discard all output buffer to avoid garbage html.
	while (ob_get_level())
		ob_end_clean();

	if ($e instanceof BaseException)
		stop($e -> code, $e -> description, $e -> status, $e);

	// Little hack to check if we got class not found error, translate
	// it to a proper class.
	$error = $e;
	$matches = null;

	$re = '/Class \"(.+)\" not found/';
	if (preg_match($re, $e -> getMessage(), $matches)) {
		$error = new ClassNotFound($matches[1]);
		$error -> applyFrom($e);
	}

	$re = '/Interface \"(.+)\" not found/';
	if (preg_match($re, $e -> getMessage(), $matches)) {
		$error = new InterfaceNotFound($matches[1]);
		$error -> applyFrom($e);
	}

	stop(1000 + $error -> getCode(), $error -> getMessage(), 500, $error);
}

/**
 * Types PHP cannot recover from. Everything else that reaches
 * {@see error_get_last()} was either handled by {@see ErrorHandler()} or
 * deliberately suppressed, and must not take over the response.
 */
function isFatal(?array $error): bool {
	if (empty($error))
		return false;

	return in_array((int) ($error["type"] ?? 0), array(
		E_ERROR,
		E_PARSE,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
		E_RECOVERABLE_ERROR
	), true);
}

/**
 * Run one shutdown step, keeping its failure to itself.
 *
 * A step that throws here would otherwise skip every step after it, and the
 * request is already over: there is nothing left to report the failure to
 * besides the server log.
 */
function step(string $name, callable $handler): void {
	try {
		$handler();
	} catch (\Throwable $e) {
		error_log("[blink][shutdown] {$name} failed: {$e}");
	}
}

function ShutdownHandler() {
	if (!defined("BLINK_SHUTTING_DOWN"))
		define("BLINK_SHUTTING_DOWN", true);

	$error = error_get_last();

	if (isFatal($error)) {
		// Discard all output buffer to avoid garbage html.
		while (ob_get_level())
			ob_end_clean();

		$fatal = new FatalError($error["type"], $error["message"]);
		$fatal -> file = $error["file"];
		$fatal -> line = $error["line"];

		// Renders the error page and terminates. The steps below never get
		// their turn, which is the right call while the request is in a state
		// none of them can trust.
		stop($error["type"], $error["message"], 500, $fatal);
	}

	// FastCGI hands the response to the client before shutdown functions
	// finish, so everything below costs the client nothing.
	if (function_exists("fastcgi_finish_request"))
		fastcgi_finish_request();

	step("modules.detach", function () {
		\Blink::detachModules();
	});

	step("autoload.save", function () {
		\Blink\Autoload\save();
	});
}

set_exception_handler("Blink\\Handlers\\ExceptionHandler");
set_error_handler("Blink\\Handlers\\ErrorHandler", E_ALL);
register_shutdown_function("Blink\\Handlers\\ShutdownHandler");
