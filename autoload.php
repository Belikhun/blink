<?php
/**
 * System autoload logics.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Autoload;

use Blink\Exception\ClassNotDefined;
use function Blink\fileGet;
use function Blink\filePut;
use function Blink\getFiles;

global $AUTOLOAD_MAP, $AUTOLOADED, $AUTOLOAD_CHANGED, $AUTOLOAD_MISSES, $AUTOLOAD_SCAN;

/**
 * Resolved class to file path map, restored from and written back to the cache.
 *
 * @var array<string, string>
 */
$AUTOLOAD_MAP = array();

/**
 * Classes loaded during this request, in load order, for the error page.
 *
 * @var array<string, string>
 */
$AUTOLOADED = array();

$AUTOLOAD_CHANGED = false;

/**
 * Paths tried for each class the autoloader could not resolve. The error page
 * shows them, which is usually enough to spot a misplaced or misnamed file.
 *
 * @var array<string, string[]>
 */
$AUTOLOAD_MISSES = array();

/**
 * Index built by the content scan, or null while the scan has not run.
 *
 * @var ?array<string, string>
 */
$AUTOLOAD_SCAN = null;

const CACHE_FILE = "/autoload.php";

/**
 * Namespace prefix to directory map, longest prefix first.
 *
 * @return array<string, string>
 */
function prefixes(): array {
	static $prefixes = null;

	if ($prefixes !== null)
		return $prefixes;

	$prefixes = array();

	foreach (\CONFIG::$AUTOLOAD as $prefix => $directory)
		$prefixes[trim($prefix, "\\") . "\\"] = rtrim(str_replace("\\", "/", $directory), "/");

	// Assigned last so an application cannot map blink's own namespace
	// somewhere else and lock itself out of the framework.
	$prefixes["Blink\\"] = CORE_ROOT . "/classes";

	uksort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));
	return $prefixes;
}

/**
 * File paths a class could live in, in the order they are tried.
 *
 * @return string[]
 */
function candidates(string $class): array {
	if ($class === "Blink")
		return array( CORE_ROOT . "/classes/Blink.php" );

	$paths = array();

	foreach (prefixes() as $prefix => $directory) {
		if (!str_starts_with($class, $prefix))
			continue;

		$relative = str_replace("\\", "/", substr($class, strlen($prefix)));
		$short = basename($relative);

		$paths[] = "{$directory}/{$relative}.php";

		// A class whose file sits in a folder of its own name, which is how
		// blink groups a subsystem with its parts: `Blink\Http\Router` lives
		// in `Http/Router/Router.php` next to the routes it owns.
		$paths[] = "{$directory}/{$relative}/{$short}.php";
	}

	return $paths;
}

function isDefined(string $class): bool {
	return class_exists($class, false)
		|| interface_exists($class, false)
		|| trait_exists($class, false);
}

function load(string $class, string $path): void {
	global $AUTOLOAD_MAP, $AUTOLOADED, $AUTOLOAD_CHANGED;

	require_once $path;

	if (!isDefined($class))
		throw new ClassNotDefined($class, $path);

	if (($AUTOLOAD_MAP[$class] ?? null) !== $path) {
		$AUTOLOAD_MAP[$class] = $path;
		$AUTOLOAD_CHANGED = true;
	}

	$AUTOLOADED[$class] = $path;
}

/**
 * Index every class declared in the directories listed in `CONFIG::$INCLUDES`.
 *
 * Path resolution misses two shapes this catches: a file declaring more than
 * one class, and a class in the global namespace. Running it costs a read of
 * every file in those directories, so it only happens once a request, and only
 * after path resolution has already failed.
 *
 * @return array<string, string>
 */
function scan(): array {
	global $AUTOLOAD_SCAN;

	if ($AUTOLOAD_SCAN !== null)
		return $AUTOLOAD_SCAN;

	$AUTOLOAD_SCAN = array();
	$nsre = '/^namespace ([a-zA-Z0-9\\\\]+);/mi';
	$clre = '/^(?:abstract\s+|final\s+|readonly\s+)*(?:class|trait|enum|interface)\s+([a-zA-Z0-9\_]+)/m';

	foreach (\CONFIG::$INCLUDES as $include) {
		if (!file_exists($include))
			continue;

		foreach (getFiles($include, "php") as $file) {
			$path = str_replace("\\", "/", $file -> getPathname());
			$content = fileGet($path, "");

			if (!preg_match_all($clre, $content, $classes))
				continue;

			$namespace = preg_match($nsre, $content, $match)
				? trim($match[1]) . "\\"
				: "";

			foreach ($classes[1] as $name) {
				$name = $namespace . trim($name);

				if (!isset($AUTOLOAD_SCAN[$name]))
					$AUTOLOAD_SCAN[$name] = $path;
			}
		}
	}

	return $AUTOLOAD_SCAN;
}

function AutoloadClass(string $class): void {
	global $AUTOLOAD_MAP, $AUTOLOAD_CHANGED, $AUTOLOAD_MISSES;

	$class = ltrim($class, "\\");

	if (!empty($AUTOLOAD_MAP[$class])) {
		$cached = $AUTOLOAD_MAP[$class];

		if (file_exists($cached)) {
			load($class, $cached);
			return;
		}

		// The file moved or went away since the cache was written. Drop the
		// entry and resolve it the long way.
		unset($AUTOLOAD_MAP[$class]);
		$AUTOLOAD_CHANGED = true;
	}

	$tried = array();

	foreach (candidates($class) as $path) {
		$tried[] = $path;

		if (!file_exists($path))
			continue;

		load($class, $path);
		return;
	}

	$scanned = scan()[$class] ?? null;

	if (!empty($scanned)) {
		load($class, $scanned);
		return;
	}

	$AUTOLOAD_MISSES[$class] = $tried;
}

function restore(): void {
	global $AUTOLOAD_MAP, $AUTOLOAD_CHANGED;

	$path = DATA_ROOT . CACHE_FILE;

	if (!file_exists($path))
		return;

	$data = require $path;

	// A cache written for another version of blink, the application or the
	// module list can point at files that have since moved.
	if (!is_array($data) || ($data["@version"] ?? null) !== \Blink::cacheVersion()) {
		$AUTOLOAD_CHANGED = true;
		return;
	}

	unset($data["@version"]);
	$AUTOLOAD_MAP = $data;
}

function save(): void {
	global $AUTOLOAD_MAP, $AUTOLOAD_CHANGED;

	if (!$AUTOLOAD_CHANGED)
		return;

	$lines = array(
		"<?php",
		"",
		"// Autoload file generated by Blink ✨",
		"",
		"return array(",
		"\t'@version' => '" . \Blink::cacheVersion() . "',"
	);

	ksort($AUTOLOAD_MAP);

	foreach ($AUTOLOAD_MAP as $class => $path)
		$lines[] = "\t'" . addslashes($class) . "' => '" . addslashes($path) . "',";

	$lines[] = ");";
	$lines[] = "";

	filePut(DATA_ROOT . CACHE_FILE, implode("\n", $lines));
	$AUTOLOAD_CHANGED = false;
}

function purge(): void {
	global $AUTOLOAD_MAP, $AUTOLOAD_CHANGED;

	$AUTOLOAD_MAP = array();
	$AUTOLOAD_CHANGED = true;
	save();
}

spl_autoload_register("Blink\\Autoload\\AutoloadClass");
restore();
