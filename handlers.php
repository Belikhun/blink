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

use Blink\ErrorPage;
use Blink\ErrorPage\ResponseAdapter;
use Blink\ErrorPage\TerminalRenderer;
use Blink\Exception\BaseException;
use Blink\Exception\ClassNotFound;
use Blink\Exception\FatalError;
use Blink\Exception\InterfaceNotFound;
use Blink\Exception\RuntimeError;

/**
 * Give the engine's own "not found" errors a blink class, so the error page
 * has a tip for them.
 */
function translate(\Throwable $e): \Throwable {
	if ($e instanceof BaseException)
		return $e;

	if (preg_match('/Class "(.+)" not found/', $e -> getMessage(), $matches)) {
		$error = new ClassNotFound($matches[1]);
		$error -> applyFrom($e);
		return $error;
	}

	if (preg_match('/Interface "(.+)" not found/', $e -> getMessage(), $matches)) {
		$error = new InterfaceNotFound($matches[1]);
		$error -> applyFrom($e);
		return $error;
	}

	return $e;
}

/**
 * Turn a PHP error into an exception.
 *
 * Throwing rather than rendering is what lets a caller decide. A `@`-prefixed
 * call, a `try` around something that is allowed to fail, a library that
 * checks a return value: all of them are defeated by a handler that renders
 * the error page and terminates, and blink relies on several of them itself.
 *
 * Anything nobody catches still reaches {@see ExceptionHandler()} and is
 * reported exactly as before.
 */
function ErrorHandler(int $code, string $text, string $file, int $line) {
	// How PHP signals `@`, and the error_reporting level generally. Returning
	// false hands the error back to PHP's own handling, which for a
	// suppressed one means ignoring it.
	if (!(error_reporting() & $code))
		return false;

	$error = new RuntimeError(1000 + $code, $text);
	$error -> file = $file;
	$error -> line = $line;

	throw $error;
}

function ExceptionHandler(\Throwable $e) {
	$e = translate($e);

	if ($e instanceof BaseException) {
		ResponseAdapter::handleThrowable($e, $e -> code, $e -> description, $e -> status);
		return;
	}

	ResponseAdapter::handleThrowable($e, 1000 + $e -> getCode(), $e -> getMessage(), 500);
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

function fatalFrom(array $error): FatalError {
	$fatal = new FatalError($error["type"], $error["message"]);
	$fatal -> file = $error["file"];
	$fatal -> line = $error["line"];
	return $fatal;
}

/**
 * Make room to report a request that ran out of memory.
 *
 * The limit was hit with everything the request allocated still alive, so
 * without more headroom building the report is the next thing to fail, and
 * that one fails silently.
 */
function reserveMemory(array $error): void {
	if (!str_contains($error["message"] ?? "", "Allowed memory size"))
		return;

	$limit = trim((string) ini_get("memory_limit"));

	if ($limit === "" || $limit === "-1")
		return;

	$value = (int) $limit;
	$bytes = match (strtoupper(substr($limit, -1))) {
		"G" => $value << 30,
		"M" => $value << 20,
		"K" => $value << 10,
		default => $value
	};

	ini_set("memory_limit", (string) ($bytes + 64 * 1024 * 1024));
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

/**
 * The teardown both shutdown handlers share.
 */
function shutdownSteps(): void {
	// An error earlier in the request can abort a construction without
	// unwinding Essence's constructor stack. Clearing it keeps the steps below
	// from tripping over a circular dependency that never happened.
	step("essence.clear", function () {
		\Blink\Essence::clearConstructorStack();
	});

	// A module that threw during boot may never have reached its bindings, and
	// the steps below still have to resolve blink's own services.
	step("essence.core", function () {
		\Blink::registerCoreSingletons();
	});

	// Handlers that asked to run once the response was out. Before the module
	// teardown, since they may still need what a module wired up.
	step("lightrail.shutdown", function () {
		\Blink\Lightrail::coordinator() -> flushShutdownDispatches();
	});

	step("modules.detach", function () {
		\Blink::detachModules();
	});

	// Last of the writes: the trail buffered during the request, including
	// anything the steps above added to it.
	step("lightrail.trail", function () {
		\Blink\Essence::make(\Blink\Lightrail\Service\Recorder::class) -> flush();
	});

	// The audit entries the request queued, in one insert.
	step("trace.flush", function () {
		\Blink\Essence::make(\Blink\Trace\Writer::class) -> flush();
	});

	step("autoload.save", function () {
		\Blink\Autoload\save();
	});
}

function ShutdownHandler() {
	if (!defined("BLINK_SHUTTING_DOWN"))
		define("BLINK_SHUTTING_DOWN", true);

	$error = error_get_last();

	if (isFatal($error)) {
		reserveMemory($error);

		// Rendered without exiting. An exit here would end this function, and
		// the steps below would never get their turn.
		step("errorpage.fatal", function () use ($error) {
			ResponseAdapter::handleThrowable(fatalFrom($error), $error["type"], $error["message"], 500, terminate: false);
		});
	}

	// FastCGI hands the response to the client before shutdown functions
	// finish, so everything below costs the client nothing.
	if (function_exists("fastcgi_finish_request"))
		fastcgi_finish_request();

	shutdownSteps();
}

/**
 * Report a failure to a terminal, where the HTML error page would only be
 * thousands of lines of markup scrolling past. The report is still stored, so
 * `error:list` finds it.
 */
function printToTerminal(\Throwable $e): void {
	try {
		TerminalRenderer::print(ErrorPage::fromException($e) -> save());
	} catch (\Throwable $reportError) {
		$stream = defined("STDERR") ? STDERR : fopen("php://stderr", "w");
		fwrite($stream, PHP_EOL . get_class($e) . ": " . strip_tags($e -> getMessage()) . PHP_EOL);
		fwrite($stream, "  at " . \Blink\getRelativePath($e -> getFile()) . ":" . $e -> getLine() . PHP_EOL);
		fwrite($stream, "  (the report could not be stored: " . $reportError -> getMessage() . ")" . PHP_EOL);
	}
}

function CliExceptionHandler(\Throwable $e) {
	printToTerminal(translate($e));
	exit(\Blink\Console::EXIT_FAILURE);
}

function CliShutdownHandler() {
	if (!defined("BLINK_SHUTTING_DOWN"))
		define("BLINK_SHUTTING_DOWN", true);

	$error = error_get_last();

	if (isFatal($error)) {
		reserveMemory($error);

		step("errorpage.fatal", function () use ($error) {
			printToTerminal(fatalFrom($error));
		});
	}

	shutdownSteps();
}

if (BLINK_IS_CLI) {
	set_exception_handler("Blink\\Handlers\\CliExceptionHandler");
	set_error_handler("Blink\\Handlers\\ErrorHandler", E_ALL);
	register_shutdown_function("Blink\\Handlers\\CliShutdownHandler");
} else {
	set_exception_handler("Blink\\Handlers\\ExceptionHandler");
	set_error_handler("Blink\\Handlers\\ErrorHandler", E_ALL);
	register_shutdown_function("Blink\\Handlers\\ShutdownHandler");
}
