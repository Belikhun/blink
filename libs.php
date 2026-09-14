<?php

/**
 * Core libraries. Formerly `belibrary.php`.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;

use Blink\Cache;
use Blink\Environment;
use Blink\Exception\BaseException;
use Blink\Exception\CodingError;
use Blink\Exception\FileNotFound;
use Blink\Exception\FileReadError;
use Blink\Exception\InvalidValue;
use Blink\Exception\JSONDecodeError;
use Blink\Exception\MissingParam;
use Blink\FileIO;
use Blink\Metric\FileMetric;
use Blink\Http\Response;
use Blink\Server;
use Blink\Template;
use Blink\URL;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

require_once "consts.php";

/**
 * Return the current request's headers, mapped by canonical header name.
 *
 * `getallheaders()` only exists under a handful of SAPIs, and the `$_SERVER`
 * reconstruction below is the one that always works, so blink never calls the
 * native function.
 *
 * @return	array<string, string>
 */
function requestHeaders(): array {
	$headers = array();

	foreach ($_SERVER as $name => $value) {
		if (!str_starts_with($name, "HTTP_"))
			continue;

		$name = ucwords(strtolower(str_replace("_", " ", substr($name, 5))));
		$headers[str_replace(" ", "-", $name)] = $value;
	}

	// Neither carries the HTTP_ prefix, and a request body cannot be read
	// without them.
	foreach (array( "CONTENT_TYPE" => "Content-Type", "CONTENT_LENGTH" => "Content-Length" ) as $key => $header) {
		if (isset($_SERVER[$key]))
			$headers[$header] = $_SERVER[$key];
	}

	return $headers;
}

class StopClock {
	public float $start;

	public function __construct() {
		$this -> start = microtime(true);
	}

	public function stop() {
		return (microtime(true) - $this -> start);
	}
}

/**
 * Safe JSON Parsing
 *
 * This function will throw an error if there is problem
 * while parsing json data.
 *
 * @param	string	$json	JSON String
 * @param	string	$path	(Optional) Provide json file path to show in the error message
 * @throws	JSONDecodeError
 * @return	array|object
 */
function safeJSONParsing(string $json, string $path = "", bool $assoc = false) {
	// Temporary disable `NOTICE` error reporting
	// to try unserialize data without triggering `E_NOTICE`
	set_error_handler(null, 0);
	$json = json_decode($json, $assoc);
	restore_error_handler();

	if ($json === null)
		throw new JSONDecodeError($path, json_last_error_msg(), array(
			"code" => json_last_error(),
			"message" => json_last_error_msg()
		));

	return $json;
}

/**
 * Generate slug from supplied text.
 *
 * @param	string	$text
 * @return	string
 */
function slugify($text) {
	$rules = <<<'RULES'
		:: Any-Latin;
		:: NFD;
		:: [:Nonspacing Mark:] Remove;
		:: NFC;
		:: [^-[:^Punctuation:]] Remove;
		:: Lower();
		[:^L:] { [-] > ;
		[-] } [:^L:] > ;
		[-[:Separator:]]+ > '-';
	RULES;

	$text = \Transliterator::createFromRules($rules)
		-> transliterate($text);

	return $text;
}

/**
 * Describe how far a timestamp is from now, in words.
 *
 * @param	int		$timestamp	The moment to describe.
 * @param	?int	$to			What to measure against. Defaults to now.
 * @return	string
 */
function relativeTime(int $timestamp, int $to = null) {
	if (empty($to))
		$to = time();

	if ($timestamp === $to)
		return __("time.now");

	$units = array( "year" => 31536000, "day" => 86400, "hour" => 3600, "minute" => 60, "second" => 1 );
	$delta = abs($timestamp - $to);
	$future = $timestamp > $to;

	$unit = "second";
	$value = $delta;

	foreach ($units as $name => $seconds) {
		$unit = $name;
		$value = $delta / $seconds;

		if ($value >= 1)
			break;
	}

	$value = (int) $value;

	// A single day or year each side of now reads better named than counted.
	if ($value === 1 && ($unit === "day" || $unit === "year"))
		return __("time.{$unit}." . ($future ? "next" : "last"));

	// English needs the singular; the languages that do not simply map both
	// keys to the same string.
	$unitKey = "time.unit.{$unit}" . ($value === 1 ? ".one" : "");

	return __("time." . ($future ? "in" : "ago"), array(
		"value" => $value,
		"unit" => __($unitKey)
	));
}

/**
 * Makes sure the data is using valid utf8, invalid characters are discarded.
 *
 * @param	mixed	$value
 * @return	mixed	with proper utf-8 encoding
 */
function cleanUTF8($value) {
	if (is_null($value) || $value === "")
		return $value;

	if (is_string($value)) {
		if ((string)(int) $value === $value)
			return $value;

		// No null bytes expected in our data, so let's remove it.
		$value = str_replace("\0", "", $value);

		static $buggyiconv = null;
		if ($buggyiconv === null) {
			$buggyiconv = (!function_exists("iconv") or @iconv("UTF-8", "UTF-8//IGNORE", "100".chr(130)."€") !== "100€");
		}

		if ($buggyiconv) {
			if (function_exists("mb_convert_encoding")) {
				$subst = mb_substitute_character();
				mb_substitute_character("none");
				$result = mb_convert_encoding($value, "utf-8", "utf-8");
				mb_substitute_character($subst);
			} else {
				$result = $value;
			}

		} else {
			$result = @iconv("UTF-8", "UTF-8//IGNORE", $value);
		}

		return $result;
	}

	if (is_array($value)) {
		foreach ($value as $k => $v)
			$value[$k] = cleanUTF8($v);

		return $value;
	}

	if (is_object($value)) {
		$value = clone($value);
		foreach ($value as $k => $v)
			$value -> $k = cleanUTF8($v);

		return $value;
	}

	// This is some other type, no utf-8 here.
	return $value;
}

/**
 * This function is used to clean, or cast value into
 * different type.
 *
 * @param	mixed	$param		Target variable to clean
 * @param	string	$type		Target variable type
 * @return	mixed
 */
function cleanParam($param, $type) {
	if (is_array($param) || is_object($param))
		throw new CodingError("cleanParam(): this function does not accept array or object!");

	switch ($type) {
		case TYPE_INT:
			return (int) $param;

		case TYPE_FLOAT:
			return (float) $param;

		case TYPE_DOUBLE:
			return (double) $param;

		case TYPE_TEXT:
		case TYPE_STRING:
			$param = cleanUTF8($param);
			return strip_tags($param);

		case TYPE_RAW:
			return cleanUTF8($param);

		case TYPE_BOOL:
			if (is_bool($param))
				return $param;

			$p = strtolower($param);

			if ($p === "on" || $p === "yes" || $p === "true")
				$param = true;
			else if ($p === "off" || $p === "no" || $p === "false")
				$param = false;
			else
				$param = empty($param);

			return $param;

		case TYPE_JSON:
			return safeJSONParsing($param, "cleanParam");

		case TYPE_JSON_ASSOC:
			return safeJSONParsing($param, "cleanParam", true);

		default:
			throw new BaseException(-1, "cleanParam(): unknown param type: $type", 400);
	}
}

/**
 * Validate an value.
 * Return false or throw an exception if value is not an valid
 * format of provided type.
 *
 * @param	mixed		$value		Value to check
 * @param	string		$type		Value type
 * @param	bool		$throw		Throw an exception or just return false?
 *
 * @return	bool
 * @throws	InvalidValue
 */
function validate($value, $type, $throw = true) {
	$valid = false;

	switch ($type) {
		case TYPE_INT:
			$valid = (is_int($value) || ctype_digit($value));
			break;

		case TYPE_FLOAT:
			$valid = (is_float($value) || is_numeric($value));
			break;

		case TYPE_EMAIL:
			$valid = (bool) preg_match(
				"/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/", $value);
			break;

		case TYPE_USERNAME:
			$valid = (bool) preg_match("/^[a-zA-Z0-9]+$/", $value);
			break;

		case TYPE_PHONE:
			$valid = (bool) preg_match("/^0[0-9]{9,11}$/", $value);
			break;

		case TYPE_TEXT:
		case TYPE_RAW:
			$valid = true;
			break;

		default:
			throw new CodingError("validate(): unknown param type: $type");
	}

	if (!$valid) {
		if ($throw)
			throw new InvalidValue($value, $type);

		return false;
	}

	return true;
}

/**
 * Return the first item that make the callback return true.
 *
 * @template	T
 * @param		T[]						$items
 * @param		string|callable|null	$callable	A callable function that will ran though each item.
 * @return		?T
 */
function first(array $items, $callable = null) {
	if (!empty($callable) && !is_callable($callable))
		throw new CodingError("first(): callable is not callable!");

	foreach ($items as $item) {
		if (is_callable($item))
			$item = $item();

		if (!empty($callable)) {
			if (!!$callable($item))
				return $item;
		} else {
			if (!!$item)
				return $item;
		}
	}

	return null;
}

/**
 * Gets the value of an environment variable.
 *
 * @param	string		$key		Environment variable name.
 * @param	mixed		$default	Default value when it's not set.
 * @param	string		$type
 *
 * @return	mixed
 */
function env(string $key, $default = null, string $type = TYPE_TEXT) {
	if (!isset(Environment::$values[$key]))
		return $default;

	return cleanParam(Environment::$values[$key], $type);
}

function template(string $name, array $context = array()) {
	echo Template::render($name, $context);
}

function view(string $name, array $context = array()) {
	return new Response(Template::render($name, $context));
}

/**
 * Returns a particular value for the named variable, taken from
 * POST or GET, otherwise returning a given default.
 *
 * @param	string		$name		Param name
 * @param	string		$type
 * @param	mixed		$default
 */
function getParam(string $name, $type = TYPE_TEXT, $default = null) {
	if (isset($_POST[$name]))
		$param = $_POST[$name];
	else if (isset($_GET[$name]))
		$param = $_GET[$name];
	else
		return $default;

	return cleanParam($param, $type);
}

/**
 * Returns a particular value for the named variable, taken from
 * POST or GET. If the parameter doesn"t exist then an error is
 * thrown because we require this variable.
 *
 * @param	string		$name		Param name
 * @param	string		$type
 * @param	mixed		$default
 * @throws	MissingParam
 */
function requiredParam(string $name, $type = TYPE_TEXT) {
	$param = getParam($name, $type);

	if ($param === null)
		throw new MissingParam($name);

	return $param;
}

/**
 * Returns a particular value for the named variable, taken from
 * request headers. This is done in a case-insensitive matter.
 *
 * @param	string		$name		Header name
 * @param	string		$type
 * @param	mixed		$default
 */
function getHeader(string $name, $type = TYPE_TEXT, $default = null) {
	$param = null;

	foreach (requestHeaders() as $key => $value) {
		if (strcasecmp($name, $key) === 0) {
			$param = $value;
			break;
		}
	}

	if ($param === null)
		return $default;

	return cleanParam($param, $type);
}

/**
 * Set Content-Type header using file extension
 *
 * @param	string	$file		File path
 * @param	string	$charset	Charset
 * @param	mixed	$default	Default value
 * @return	string|null
 */
function mime(string $file, string $charset = "utf-8", $default = "text/plain") {
	$mime = null;
	$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

	if (!empty(MIME_TYPES[$extension])) {
		$mime = MIME_TYPES[$extension];

		if (!empty($charset))
			$mime .= "; charset={$charset}";
	}

	if (empty($mime) && function_exists("mime_content_type"))
		$mime = mime_content_type($file);

	if (empty($mime))
		$mime = $default;

	return $mime;
}

function expire(int $time) {
	header("Cache-Control: public, max-age=$time");
	header("Expires: " . gmdate("D, d M Y H:i:s \G\M\T", time() + $time));
	header_remove("Pragma");
}

/**
 * Return new path relative to webserver's root path.
 *
 * @return	string
 */
function getRelativePath(string $fullPath, string $separator = "/", string $base = BASE_PATH) {
	if ($separator === "/") {
		$search = ($base === BASE_PATH)
			? $base
			: str_replace("\\", "/", $base);

		$subject = str_replace("\\", "/", $fullPath);
	} else {
		$search = preg_replace('/(\\\\|\/)/m', $separator, $base);
		$subject = preg_replace('/(\\\\|\/)/m', $separator, $fullPath);
	}

	$result = str_replace($search, "", $subject);

	// If the result does not end up with directory slash (`/`),
	// it might be that the directory name has been chopped in half.
	// Return the original path instead.
	if ($result[0] !== $separator)
		return $fullPath;

	return str_replace($search, "", $subject);
}

/**
 * Return the requestor's client IP address.
 *
 * @return	string
 */
function getClientIP(): string {
	return Server::$CLIENT_IP;
}

/**
 * Stringify a subject for displaying purpose.
 *
 * @param	mixed		$subject
 * @return	string
 */
function stringify($subject) {
	$output = "";

	if (is_callable($subject, true)) {
		if (is_array($subject)) {
			$class = new \ReflectionClass($subject[0]);
			$info = $class -> getMethod($subject[1]);
			$output = $class -> getName() . ($info -> isStatic() ? "::" : " -> ") . $info -> getName();
		} else if (is_string($subject) && str_contains($subject, "::")) {
			// A `Class::method` string is a method, and asking
			// `ReflectionFunction` about one only reports that no function by
			// that name exists.
			$info = new \ReflectionMethod($subject);
			$output = $info -> class . ($info -> isStatic() ? "::" : " -> ") . $info -> getName();
		} else {
			$info = new \ReflectionFunction($subject);
			$output = $info -> getName();
		}

		// A built-in has no file to point at.
		if ($info -> getFileName() !== false)
			$output .= " (" . getRelativePath($info -> getFileName()) . ":" . $info -> getStartLine() . ")";
	} else if (is_object($subject)) {
		$output = get_class($subject);

		if (method_exists($subject, "__toString"))
			$output .= " \"{$subject}\"";
	} else if (is_array($subject)) {
		$output = "[]";

		if (!empty($subject)) {
			$output = array();

			foreach ($subject as $key => $value)
				$output[] = "$key = " . htmlspecialchars($value);

			$output = implode(", ", $output);
			$output = "[ {$output} ]";
		}
	} else if (is_bool($subject)) {
		$output = $subject ? "true" : "false";
	} else if (is_numeric($subject)) {
		$output = (string) $subject;
	} else if ($subject === null) {
		$output = "[NULL]";
	} else {
		$output = (string) $output;
	}

	return $output;
}

/**
 * Return human readable Size
 *
 * @param	int		$bytes		Size in byte
 * @return	string	Readable Size
 */
function convertSize(int $bytes) {
	$sizes = array("B", "KB", "MB", "GB", "TB");
	for ($i = 0; $bytes >= 1024 && $i < (count($sizes) - 1); $bytes /= 1024, $i++);

	return (round($bytes, 2) . " " . $sizes[$i]);
}

/**
 * Return human readable Time
 *
 * @param	float		$time		Time
 * @return	string		Readable time
 */
function convertTime(float $time) {
	$units = array(
		"µs" => 0.000001,
		"ms" => 0.001,
		"s" => 1,
		"m" => 60,
		"h" => 3600,
		"d" => 86400
	);

	$value = 0;
	$unit = "";

	foreach ($units as $u => $uv) {
		$v = $time / $uv;
		if ($v < 1)
			break;

		$unit = $u;
		$value = $v;
	}

	return sprintf("%1.2f%s", $value, $unit);
}

class FileWithPriority {
	public string $path;
	public int $priority;

	public function __construct(string $path, int $priority) {
		$this -> path = $path;
		$this -> priority = $priority;
	}

	public function __serialize() {
		return array(
			$this -> path,
			$this -> priority
		);
	}

	public function __unserialize(array $data) {
		list(
			$this -> path,
			$this -> priority
		) = $data;
	}
}

/**
 * Glob files and sort them by priority defined by
 * `@priority` in comment doc.
 *
 * It"s a good idea to cache the return data of this function as
 * reading big files and performing a regex match with a large
 * string take a lot of time.
 *
 * @param	string		$pattern	Pattern relative to web root.
 * @return	FileWithPriority[]
 */
function globFilesPriority($pattern, int $flags = 0) {
	$files = glob(BASE_PATH . $pattern, $flags);
	$list = array();

	// Parse files to priority list
	foreach ($files as $file) {
		$content = (new FileIO($file)) -> read();
		$item = new FileWithPriority($file, 0);
		$matches = null;

		if (preg_match("/\@priority(?:[\s\t]+)(\d+)/m", $content, $matches))
			$item -> priority = (int) $matches[1];

		$list[] = $item;
	}

	usort($list, function ($a, $b) {
		return $b -> priority <=> $a -> priority;
	});

	return $list;
}

/**
 * Glob files and sort them by priority defined by
 * `@priority` in comment doc. Return cached data if possible.
 *
 * @param	string		$pattern	Pattern relative to web root.
 * @return	FileWithPriority[]
 */
function globFilesPriorityCached($pattern, int $flags = 0) {
	$id = md5($pattern . $flags);
	$cache = new Cache($id);

	if (!$cache -> fetch()) {
		$data = globFilesPriority($pattern, $flags);

		$cache -> initialize()
			-> setContent($data)
			-> save();

		return $data;
	}

	return $cache -> content();
}

/**
 * Redirect to target URL.
 *
 * @param	string|URL		$url
 * @return	void
 */
function redirect($url) {
	if ($url instanceof URL)
		$url = $url -> out(false);

	if (headers_sent()) {
		echo "<script>location.href = `/error?redirect=true`;</script>";
		die();
	}

	header("Location: $url");
	die();
}

/**
 * Loop through all files inside a folder, recursively.
 *
 * @param	string	$path
 * @param	string	$extension
 * @return	\Generator<\SplFileInfo>
 */
function getFiles(string $path, string $extension = "*") {
	$di = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
	$ri = new RecursiveIteratorIterator($di);
	$extension = strtolower($extension);

	foreach ($ri as $file) {
		if ($extension !== "*" && strtolower($file -> getExtension()) !== $extension)
			continue;

		yield $file;
	}
}

/**
 * Fast file get content with metric recording.
 *
 * @param	string		$path		Path to file
 * @param	string		$default	Default value
 * @return	string|null				File content or default value if failed.
 */
function fileGet(string $path, $default = null, bool $throw = false): string|null {
	$metric = null;

	if (!file_exists($path)) {
		if ($throw)
			throw new FileNotFound($path);

		return $default;
	}

	if (class_exists(FileMetric::class))
		$metric = new FileMetric("r", "text", $path);

	$content = file_get_contents($path);

	if ($content === false) {
		$metric ?-> time(-1);

		if ($throw)
			throw new FileReadError($path);

		return $default;
	}

	$metric ?-> time(!empty($content) ? mb_strlen($content, "utf-8") : -1);
	return $content;
}

/**
 * Fast file put content with metric recording.
 *
 * @param	string		$path		Path to file
 * @param	string		$content	File content
 * @return	int|null				Bytes written or null if write failed.
 */
function filePut(string $path, $content): int|null {
	$metric = null;

	if (class_exists(FileMetric::class))
		$metric = new FileMetric("w", "text", $path);

	$bytes = file_put_contents($path, $content);
	$metric ?-> time(($bytes === false) ? -1 : $bytes);
	return ($bytes === false) ? null : $bytes;
}

/**
 * Check if an array is sequential.
 * An array is considered sequential if it has consecutive integer keys starting from zero.
 *
 * @param	array	$array The array to check.
 * @return	bool	Returns true if the array is sequential, false otherwise.
 */
function isSequential($array) {
	$count = count($array);

	for ($i = 0; $i < $count; $i++)
		if (!isset($array[$i]))
			return false;

	return true;
}

/**
 * Generate Random Number
 *
 * @param	int|float		$min		Minimum random number
 * @param	int|float		$max		Maximum random number
 * @param   bool			$toInt		To return an Integer value
 * @return	int|float		Generated number
 */
function randBetween($min, $max, bool $toInt = true) {
	$rand = (float) (mt_rand() / mt_getrandmax());

	return $toInt
		? intval($rand * ($max - $min + 1) + $min)
		: ($rand * ($max - $min) + $min);
}

/**
 * Pick a random item in an array.
 *
 * @template	Item
 * @param		array<Item>		$array		The array
 * @param		int				$index		The randomized item index
 * @return		Item			Item in the array
 */
function randItem($array, &$index = 0) {
	$index = randBetween(0, count($array) - 1, true);
	return $array[$index];
}

define("RAND_CHARSET_TEXT", "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789");
define("RAND_CHARSET_HEX", "0123456789abcdef");

/**
 * Generate Random String
 *
 * @param	int			$len		Length of the randomized string
 * @param	string		$charset	Charset
 * @return	string		Generated String
 */
function randString(int $len = 16, string $charset = RAND_CHARSET_TEXT) {
	$randomString = "";
	$charsetLength = strlen($charset);

	for ($i = 0; $i < $len; $i++) {
		$p = randBetween(0, $charsetLength - 1, true);
		$randomString .= $charset[$p];
	}

	return $randomString;
}

/**
 * Trim a string if it's too long and add ellipsis after it.
 *
 * @param	string	$string	The string to be trimmed.
 * @param	int		$maxLen	The maximum length of the string before it's trimmed.
 * @return	string	The trimmed string with ellipsis added after it.
 */
function trimString(string $string, int $maxLen) {
	if (strlen($string) <= $maxLen)
		return $string;

	$string = substr($string, 0, $maxLen);
	$string = rtrim($string, " .,;:-");
	$string .= "...";
	return $string;
}

/**
 * Append to a file under an exclusive lock, so lines from concurrent writers
 * never interleave.
 *
 * @param	string|string[]	$data	Text to append, or lines each written with
 * 									a newline after it.
 */
function filePush(string $path, string|array $data): bool {
	$metric = null;

	if (class_exists(FileMetric::class))
		$metric = new FileMetric("a", "text", $path);

	$handle = @fopen($path, "a");

	if (!$handle) {
		$metric ?-> time(-1);
		throw new FileWriteError($path);
	}

	try {
		if (!flock($handle, LOCK_EX)) {
			$metric ?-> time(-1);
			throw new FileLockError($path, "another process holds the lock");
		}

		$bytes = 0;

		if (is_array($data)) {
			foreach ($data as $line)
				$bytes += (int) fwrite($handle, $line . PHP_EOL);
		} else {
			$bytes = (int) fwrite($handle, $data);
		}

		flock($handle, LOCK_UN);
		$metric ?-> time($bytes);
		return true;
	} finally {
		fclose($handle);
	}
}

/**
 * The last resort when even the error page cannot render: the bare facts as
 * preformatted text.
 */
function printException(Throwable $e): void {
	$lines = array(
		get_class($e),
		"",
		$e -> getMessage(),
		"",
		"Stacktrace:",
		$e -> getTraceAsString()
	);

	echo "<pre>" . htmlspecialchars(implode("\n", $lines)) . "</pre>";
}

/**
 * Render a report as the HTML error page.
 *
 * The application's own `error.php` gets the first try, blink's the second
 * and {@see printException()} the last, so a broken error page still shows
 * the error that led to it.
 *
 * @param	bool	$redirect	The headers are already out, so the status can
 * 								no longer change. Hop to `/error`, which renders
 * 								the page out of the session with the right one.
 * @param	bool	$terminate	Whether to end the script afterwards. False only
 * 								from a shutdown function, where an exit would
 * 								skip the steps still to run.
 */
function renderErrorPage(ErrorPage $page, bool $redirect = false, bool $terminate = true): void {
	$_SESSION["LAST_ERROR"] = $page;
	$_SERVER["REDIRECT_STATUS"] = $page -> status;

	if (!defined("BLINK_HANDLING_ERROR"))
		define("BLINK_HANDLING_ERROR", true);

	if ($redirect && !defined("ERROR_NO_REDIRECT")) {
		echo "<script>location.href = `/error?redirect=true`;</script>";

		if ($terminate)
			exit;

		return;
	}

	if (!headers_sent())
		header("Content-Type: text/html; charset=utf-8");

	$candidates = array( CORE_ROOT . "/error.php" );

	if (file_exists(BASE_PATH . "/error.php") && !defined("CUSTOM_ERROR_HANDING"))
		array_unshift($candidates, BASE_PATH . "/error.php");

	$failure = null;

	foreach ($candidates as $file) {
		// Buffered, so a page that fails halfway leaves nothing behind for
		// the next candidate to render under.
		ob_start();

		try {
			require $file;
			ob_end_flush();
			unset($_SESSION["LAST_ERROR"]);

			if ($terminate)
				exit;

			return;
		} catch (Throwable $e) {
			ob_end_clean();
			$failure = $e;
		}
	}

	printException($failure);

	if ($terminate)
		exit;
}
