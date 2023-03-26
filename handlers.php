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
 * Copyright (C) 2018-2022 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Handlers;
use Blink\Exception\BaseException;

function errorHandler(Int $code, String $text, String $file, Int $line) {
	// Diacard all output buffer to avoid garbage html.
	while (ob_get_level())
		ob_end_clean();
	
	$errorData = Array(
		"code" => $code,
		"description" => $text,
		"file" => getRelativePath($file),
		"line" => $line,
	);

	stop(-1, "Error Occurred: ". $text, 500, $errorData);
}

function exceptionHandler($e) {
	// Discard all output buffer to avoid garbage html.
	while (ob_get_level())
		ob_end_clean();

	if ($e instanceof BaseException)
		stop($e -> code, $e, $e -> status, $e -> data);
	else {
		stop(-1, get_class($e) ." [{$e -> getCode()}]: {$e -> getMessage()}", 500, Array(
			"file" => getRelativePath($e -> getFile()),
			"line" => $e -> getLine()
		));
	}
}

global $AUTOLOAD_DATA, $AUTOLOAD_MAP;
$AUTOLOAD_DATA = Array();
$AUTOLOAD_MAP = Array();

function updateAutoloadData() {
	global $AUTOLOAD_DATA, $AUTOLOAD_MAP;

	// Namespace regex
	$nsre = "/namespace ([a-zA-Z0-9\\\\]+);/mi";

	// Class name regex
	$clre = "/class ([a-zA-Z0-9]+)/mi";

	foreach (\CONFIG::$INCLUDES as $include) {
		$files = getFiles($include, "php");

		foreach ($files as $file) {
			$path = str_replace("\\", "/", $file -> getPathname());
			$path = str_replace(BASE_PATH, "", $path);
			$hash = md5($path);

			if (!empty($AUTOLOAD_MAP[$hash]))
				continue;

			$content = file_get_contents(($path[0] === "/") ? BASE_PATH . $path : $path);
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

function autoloadClass(String $class) {
	global $AUTOLOAD_DATA;

	if (empty($AUTOLOAD_DATA))
		getAutoloadData();

	if (!empty($AUTOLOAD_DATA[$class])) {
		$item = $AUTOLOAD_DATA[$class];
		require_once ($item["path"][0] === "/") ? BASE_PATH . $item["path"] : $item["path"];
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

set_exception_handler("Blink\\Handlers\\exceptionHandler");
set_error_handler("Blink\\Handlers\\errorHandler", E_ALL);
spl_autoload_register("Blink\\Handlers\\autoloadClass");
