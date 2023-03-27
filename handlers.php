<?php
/**
 * handlers.php
 * 
 * Register system handlers.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Handlers;
use Blink\Exception\BaseException;
use Blink\Middleware\Exception\ClassNotDefined;
use Blink\Middleware\Exception\InvalidDefinition;

global $ERROR_STACK;

/** @var \Exception[] */
$ERROR_STACK = Array();

function ErrorHandler(int $code, String $text, String $file, int $line) {
	global $ERROR_STACK;

	// Diacard all output buffer to avoid garbage html.
	while (ob_get_level())
		ob_end_clean();
	
	$error = new BaseException(1000 + $code, $text);
	$error -> file = $file;
	$error -> file = getRelativePath($file);
	$error -> line = $line;

	$ERROR_STACK[] = $error;
	stop($error -> code, $error -> description, 500, $error);
}

function ExceptionHandler(\Throwable $e) {
	global $ERROR_STACK;

	// Discard all output buffer to avoid garbage html.
	while (ob_get_level())
		ob_end_clean();

	$ERROR_STACK[] = $e;

	if ($e instanceof BaseException)
		stop($e -> code, $e -> description, $e -> status, $e);
	
	stop(1000 + $e -> getCode(), $e -> getMessage(), 500, $e);
}

global $AUTOLOAD_DATA, $AUTOLOAD_MAP, $AUTOLOADED;
$AUTOLOAD_DATA = Array();
$AUTOLOAD_MAP = Array();
$AUTOLOADED = Array();

function updateAutoloadData() {
	global $AUTOLOAD_DATA, $AUTOLOAD_MAP;

	// Namespace regex
	$nsre = "/namespace ([a-zA-Z0-9\\\\]+);/mi";

	// Class name regex
	$clre = "/class ([a-zA-Z0-9]+)/mi";

	foreach (\CONFIG::$INCLUDES as $include) {
		$files = getFiles($include, "php");

		foreach ($files as $file) {
			$path = getRelativePath($file -> getPathname());
			$hash = md5($path);

			if (!empty($AUTOLOAD_MAP[$hash]))
				continue;

			$content = file_get_contents(($path[0] === "/" && !str_starts_with($path, BASE_PATH))
				? BASE_PATH . $path
				: $path);
			
			$namespace = "";
			$classes = Array();

			if (!preg_match_all($clre, $content, $clmatches))
				continue;

			if (preg_match($nsre, $content, $nsmatch))
				$namespace = trim($nsmatch[1]);

			foreach ($clmatches[1] as $match)
				$classes[] = trim($match);

			$AUTOLOAD_MAP[$hash] = Array();

			foreach ($classes as $class) {
				$fullname = !empty($namespace)
					? "{$namespace}\\{$class}"
					: $class;

				$AUTOLOAD_DATA[$fullname] = Array(
					"path" => $path,
					"hash" => $hash,
					"namespace" => $namespace,
					"class" => $class,
					"name" => $fullname
				);

				$AUTOLOAD_MAP[$hash][] = $fullname;
			}
		}
	}
}

function getAutoloadData() {
	global $AUTOLOAD_DATA, $AUTOLOAD_MAP;
	$path = DATA_ROOT . "/autoload.data";

	if (!file_exists($path))
		return;

	$AUTOLOAD_DATA = unserialize(file_get_contents($path));

	foreach ($AUTOLOAD_DATA as $class => $value)
		$AUTOLOAD_MAP[$value["hash"]] = $class;
}

function saveAutoloadData() {
	global $AUTOLOAD_DATA;
	file_put_contents(DATA_ROOT . "/autoload.data", serialize($AUTOLOAD_DATA));
}

function middleware(String $class) {
	$name = explode("\\", $class);
	$name = end($name);

	$corePath = CORE_ROOT . "/middleware/{$name}.php";
	$default = CORE_ROOT . "/defaults/middleware/{$name}.php";
	$appPath = BASE_PATH . "/middleware/{$name}.php";
	$parent = "Blink\\Middleware\\{$name}";

	// Verify if this class has been defined in core.
	if (!file_exists($corePath))
		return false;

	if (file_exists($appPath)) {
		require_once $appPath;
	} else {
		if (!file_exists($default)) {
			// Create new default file for this class.
			$content = file_get_contents(CORE_ROOT . "/defaults/middleware/.template");
			$content = str_replace("{{NAME}}", $name, $content);
			$content = "<?php\n{$content}";
			file_put_contents($default, $content);
		}

		require_once $default;
	}

	// Make sure the class have been included and defined correctly.
	if (!class_exists($class))
		throw new ClassNotDefined($class);

	if (!in_array("Blink\\Middleware\\{$name}", class_parents($class, false)))
		throw new InvalidDefinition($class, $parent);

	return true;
}

function autoloadClass(String $class) {
	global $AUTOLOAD_DATA, $AUTOLOADED;

	if (str_starts_with($class, "Middleware")) {
		// Process middleware class include.
		if (middleware($class))
			return;
	}

	if (empty($AUTOLOAD_DATA))
		getAutoloadData();

	if (!empty($AUTOLOAD_DATA[$class])) {
		$item = $AUTOLOAD_DATA[$class];

		require_once ($item["path"][0] === "/" && !str_starts_with($item["path"], BASE_PATH))
			? BASE_PATH . $item["path"]
			: $item["path"];

		$AUTOLOADED[] = $item;
		return;
	}
	
	// We might have a new class here that the code is requesting. Update the
	// autoload cache and try again.
	updateAutoloadData();
	saveAutoloadData();

	if (!empty($AUTOLOAD_DATA[$class])) {
		$item = $AUTOLOAD_DATA[$class];
		require_once ($item["path"][0] === "/") ? BASE_PATH . $item["path"] : $item["path"];
		return;
	}
}

set_exception_handler("Blink\\Handlers\\ExceptionHandler");
set_error_handler("Blink\\Handlers\\ErrorHandler", E_ALL);
spl_autoload_register("Blink\\Handlers\\autoloadClass");
