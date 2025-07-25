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

use Blink\BacktraceFrame;
use Blink\Cache;
use Blink\Environment;
use Blink\Exception\BaseException;
use Blink\Exception\CodingError;
use Blink\Exception\FileNotFound;
use Blink\Exception\FileReadError;
use Blink\Exception\InvalidValue;
use Blink\Exception\JSONDecodeError;
use Blink\Exception\MissingParam;
use Blink\Exception\RuntimeError;
use Blink\FileIO;
use Blink\HtmlWriter;
use Blink\Metric\FileMetric;
use Blink\Http\Response;
use Blink\Http\Response\APIResponse;
use Blink\Server;
use Blink\Template;
use Blink\URL;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

require_once "consts.php";

if (!function_exists("getallheaders")) {
	function getallheaders() {
		$headers = [];

		foreach ($_SERVER as $name => $value)
			if (substr($name, 0, 5) == "HTTP_")
				$headers[str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($name, 5)))))] = $value;

		return $headers;
	}
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

function relativeTime(int $timestamp, int $to = null) {
	if (empty($to))
		$to = time();

	$string = "";

	if ($timestamp === $to) {
		$string = "mới đây";
	} else {
		$units = array( "năm" => 31536000, "ngày" => 86400, "giờ" => 3600, "phút" => 60, "giây" => 1 );
		$delta = abs($timestamp - $to);
		$future = $timestamp > $to;

		$unit = "";
		$value = "";

		foreach ($units as $unit => $value) {
			$value = $delta / $value;

			if ($value > 1)
				break;
		}

		if ($unit === "ngày" && $value === 1) {
			$string = ($future) ? "ngày mai" : "hôm qua";
		} else if ($unit === "năm" && $value === 1) {
			$string = ($future) ? "năm sau" : "năm ngoái";
		} else {
			$string = ((int) $value) . " $unit " . ($future ? "sau" : "trước");
		}
	}

	return $string;
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

	foreach (getallheaders() as $key => $value) {
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
		} else {
			$info = new \ReflectionFunction($subject);
			$output = $info -> getName();
		}

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
 * Render soucre code of a file to a friendly format.
 *
 * @param	string	$file	Path to file to be rendered.
 * @param	int		$line	Line number that will be highlighted.
 * @param	int		$count	Number of lines will be rendered.
 */
function renderSourceCode(string $file, int $line, int $count = 10) {
	if (!file_exists($file)) {
		echo "<code>File does not exits: {$file}</code>";
		return;
	}

	$content = htmlspecialchars(fileGet($file));
	$lines = explode("\n", $content);

	$from = $line - floor($count / 2);
	$to = $line + ceil($count / 2);
	$max = count($lines) - 1;

	if ($from < 0) {
		$to -= $from;
		$from = 0;

		if ($to > $max)
			$to = $max;
	} else if ($to > $max) {
		$from -= $to - $max;
		$to = $max;

		if ($from < 0)
			$from = 0;
	}

	// Only get the part we need.
	$lines = array_slice($lines, $from, $to - $from + 1);
	$content = implode("\n", $lines);

	// Keywords regex
	$keywords = array(	"try", "catch", "return", "public", "protected", "private", "static", "include_once",
						"include", "require_once", "require", "global", "if", "else", "use", "throw",
						"new", "\$this", "self", "echo", "print", "foreach", "for", "continue", "break", "instanceof",
						"default", "while", "switch", "case", "match", "class", "extends", "implement", "parent",
						"namespace" );
	$re = '/(^|[\t\n\(\! ])(' . implode("|", $keywords) . ')(?=[\t\n\(\{\:\; ])/mi';
	$content = preg_replace($re, '$1<span class="sc-keyword">$2</span>', $content);

	// Function regex
	$re = '/([\t\n\( ]|^)(function)(?=[\t\n\(\{ ])/mi';
	$content = preg_replace($re, '$1<span class="sc-function">$2</span>', $content);

	// Class name regex
	$re = '/(^| |\(|\t)([A-Z\\\\]{1}[a-zA-Z0-9\\\\]+)([\t\n\;\(\)\{\:\- ]|$)/m';
	$content = preg_replace($re, '$1<span class="sc-class">$2</span>$3', $content);

	// String
	$re = '/(&quot;(.*)&quot;|\'(.*)\')/mU';
	$content = preg_replace($re, '<span class="sc-string">$1</span>', $content);

	// Variables
	$re = '/(\$[a-zA-Z0-9_]+)/m';
	$content = preg_replace($re, '<span class="sc-variable">$1</span>', $content);

	// Functions
	$re = '/([a-zA-Z0-9_]+)(\()/m';
	$content = preg_replace($re, '<span class="sc-function-name">$1</span>$2', $content);

	// Comments
	$re = '/([\t ]|^)(\/\/|\*|\/\*\*)(.*)$/m';
	$content = preg_replace($re, '$1<span class="sc-comment">$2$3</span>', $content);

	// PHP tag
	$content = str_replace("&lt;?php", '<span class="sc-meta">&lt;?php</span>', $content);
	$content = str_replace("?&gt;", '<span class="sc-meta">?&gt;</span>', $content);

	$lines = explode("\n", $content);
	$numHtml = "";
	$lineHtml = "";

	for ($i = 0; $i < count($lines); $i++) {
		$code = trim($lines[$i], "\n\r");
		$classes = array( "line" );

		// Index start from 0, but file's line start from 1
		if ($i == $line - $from - 1)
			$classes[] = "current";

		$numHtml .= HtmlWriter::div(array( "class" => $classes ), $from + $i + 1);
		$lineHtml .= HtmlWriter::div(array( "class" => $classes ), "<span>{$code}</span>");
	}

	echo HtmlWriter::startDIV(array( "class" => "sourceCode" ));
	echo HtmlWriter::span(array( "class" => "nums" ), $numHtml);
	echo HtmlWriter::build("code", array( "class" => "lines" ), $lineHtml);
	echo HtmlWriter::endDIV();
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

function backtrace(int $limit = 0) {
	$data = debug_backtrace(0, $limit);
	return processBacktrace($data);
}

/**
 * Process backtrace data returned from {@see debug_backtrace()}
 * or {@see Exception::getTrace()}
 *
 * @param	\Throwable|array	$data
 * @return	BacktraceFrame[]
 */
function processBacktrace($data, bool $merges = true) {
	global $ERROR_STACK;

	$exception = null;
	$frames = array();

	if ($data instanceof Throwable) {
		$exception = $data;
		$data = ($exception instanceof BaseException)
			? $exception -> trace()
			: $exception -> getTrace();
	}

	foreach ($data as $item) {
		$frame = new BacktraceFrame($item["function"]);
		$frames[] = $frame;

		foreach ([ "file", "line", "class", "type" ] as $key) {
			if (empty($item[$key]))
				continue;

			if ($key === "file")
				$item[$key] = getRelativePath($item[$key]);

			$frame -> {$key} = $item[$key];
		}

		if (empty($item["args"]))
			continue;

		foreach ($item["args"] as $arg) {
			if (is_string($arg)) {
				if (strlen($arg) > 5 && (str_contains($arg, ".php") || realpath($arg)))
					$arg = getRelativePath($arg);
			} else if (is_object($arg)) {
				if ($arg instanceof Throwable) {
					$value = $arg -> getMessage();
					if (str_contains($value, ".php"))
						$value = getRelativePath($value);

					$arg = [ get_class($arg), $value ];
				} else if (method_exists($arg, "__toString")) {
					$value = (string) $arg;
					if (str_contains($value, ".php"))
						$value = getRelativePath($value);

					$arg = [ get_class($arg), $value ];
				} else
					$arg = get_class($arg);
			} else if (is_bool($arg)) {
				$arg = $arg ? "true" : "false";
			} else if (is_array($arg)) {
				$count = count($arg);

				if (isSequential($arg)) {
					$arg = $count > 0
						? "[ ...({$count})... ]"
						: "[]";
				} else {
					$arg = $count > 0
						? "{ ...({$count})... }"
						: "{}";
				}
			} else if ($arg === null) {
				$arg = "[NULL]";
			}

			$frame -> args[] = $arg;
		}
	}

	if (!empty($exception) && !($exception instanceof RuntimeError)) {
		$file = getRelativePath($exception -> getFile());
		$line = $exception -> getLine();

		if ($file && $line && (empty($frames) || ($frames[0] -> file !== $file || $frames[0] -> line !== $line))) {
			// Add missing top frame.
			$trace = new BacktraceFrame(get_class($exception));
			$trace -> file = $file;
			$trace -> line = $exception -> getLine();
			$trace -> fault = true;
			array_unshift($frames, $trace);
		}
	}

	if (!empty($exception) && $merges) {
		/**
		 * Merge frames.
		 * @param	BacktraceFrame[]	$from
		 * @param	BacktraceFrame[]	$target
		 */
		$merge = function (array $from, array $target, bool $reverse = false) {
			$insert = 0;

			if ($reverse)
				$target = array_reverse($target);

			foreach ($from as $merge) {
				foreach ($target as $i => &$check) {
					if ($merge -> hash() === $check -> hash()) {
						if (!empty($merge -> file) && $merge -> file)
							$check -> file = $merge -> file;

						if (!empty($merge -> line) && $merge -> line > 0)
							$check -> line = $merge -> line;

						$check -> function = $merge -> function ?: $check -> function;

						if (empty($check -> args) && !empty($merge -> args))
							$check -> args = $merge -> args;

						$insert = $i;
						continue 2;
					}
				}

				array_splice($target, $insert - ($reverse ? 1 : 0), 0, [ $merge ] );
				$insert += 1;
			}

			return $reverse
				? array_reverse($target)
				: $target;
		};

		// Add previous exceptions.
		foreach ($ERROR_STACK as $e) {
			if ($exception == $e)
				continue;

			$frames = $merge($frames, processBacktrace($e, false));
		}

		// Merge exception frames with full backtrace.
		$frames = $merge(backtrace(), $frames);

		// Reduce duplicate.
		for ($i = 0; $i < count($frames) - 1; $i++) {
			$frame = &$frames[$i];
			$next = $frames[$i + 1];

			if ($frame -> hash() === $next -> hash() || $frame -> function === $next -> function) {
				if (!empty($next -> file) && $next -> file)
					$frame -> file = $next -> file;

				if (!empty($next -> line) && $next -> line > 0)
					$frame -> line = $next -> line;

				$frame -> function = $next -> function ?: $frame -> function;

				if (empty($frame -> args) && !empty($next -> args))
					$frame -> args = $next -> args;

				array_splice($frames, $i + 1, 1);
			}
		}

		// Reset frame pointer to prevent error in next loop.
		unset($frame);

		// Update fault points.
		foreach ($ERROR_STACK as $e) {
			$file = getRelativePath($e -> getFile());
			$line = $e -> getLine();

			foreach ($frames as $frame) {
				if ($frame -> file === $file && $frame -> line === $line)
					$frame -> fault = true;
			}
		}
	}

	return $frames;
}

/**
 * Print out response data, set some header
 * and stop script execution!
 *
 * @param	int						$code			Response code
 * @param	string					$description	Response description
 * @param	int						$HTTPStatus		Response HTTP status code
 * @param	array|object|\Throwable	$data			Response data (optional)
 * @param	bool|mixed				$hashData		To hash the data/Data to hash
 * @return	void
 */
function stop(
	int $code = 0,
	string $description = "",
	int $status = 200,
	array|object $data = array(),
	$hashData = false
) {
	$response = new APIResponse($code, $description, $status, $data, $hashData);

	// Create a new error page instance!
	$instance = \Blink\ErrorPage\Instance::create($response -> output());
	$response -> set("report", (string) $instance -> url());

	$pageType = "NORMAL";
	$errored = ($status >= 300 || $code !== 0);
	$accepts = explode(",", getHeader("Accept"));

	if ($errored) {
		foreach ($accepts as $accept) {
			if (str_starts_with($accept, "text/html")) {
				$pageType = "NORMAL";
				break;
			}

			if (str_starts_with($accept, "application/json")) {
				$pageType = "API";
				break;
			}
		}
	} else {
		if (!defined("PAGE_TYPE"))
			define("PAGE_TYPE", "NORMAL");

		$pageType = strtoupper(PAGE_TYPE);
	}

	switch ($pageType) {
		case "NORMAL":
			if (!headers_sent()) {
				$response -> header("Output", $response -> code);
				$response -> header("Report-ID", $instance -> id);
				$response -> serve();
			}

			if ($errored)
				renderErrorPage($instance, headers_sent());

			break;

		case "API":
			$response -> header("Access-Control-Allow-Origin", "*");
			$response -> header("Report-ID", $instance -> id);
			echo $response -> serve();
			break;

		default:
			print "<h1>Code $status</h1><p>$description</p>";
			break;
	}

	die();
}

function printException(Throwable $e) {
	$lines = array();

	$lines[] = get_class($e);
	$lines[] = "";
	$lines[] = $e -> getMessage();
	$lines[] = "Stacktrace:";
	$lines[] = $e -> getTraceAsString();

	echo "<pre>" . implode("\n", $lines) . "</pre>";
	die();
}

function renderErrorPage(\Blink\ErrorPage\Instance $data, bool $redirect = false) {
	$_SESSION["LAST_ERROR"] = $data;
	$_SERVER["REDIRECT_STATUS"] = $data -> status;

	if (file_exists(BASE_PATH . "/error.php") && !defined("CUSTOM_ERROR_HANDING")) {
		if ($redirect && !defined("ERROR_NO_REDIRECT"))
			redirect("/error?redirect=true");

		define("CUSTOM_ERROR_HANDING", true);
		header("Content-Type: text/html; charset=utf-8");

		try {
			require BASE_PATH . "/error.php";
			unset($_SESSION["LAST_ERROR"]);
			die();
		} catch (Throwable $e) {
			try {
				\Blink\Handlers\ExceptionHandler($e);
				die();
			} catch (Throwable $e) {
				printException($e);
			}
		}
	}

	// Fall back to built in error page.
	if (!headers_sent())
		header("Content-Type: text/html; charset=utf-8");

	try {
		require CORE_ROOT . "/error.php";
		unset($_SESSION["LAST_ERROR"]);
		die();
	} catch (Throwable $e) {
		try {
			\Blink\Handlers\ExceptionHandler($e);
			die();
		} catch (Throwable $e) {
			printException($e);
		}
	}
}
