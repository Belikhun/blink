<?php

/**
 * libs.php
 * 
 * Core libraries. Formerly `belibrary.php`.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

use Blink\Exception\BaseException;
use Blink\Exception\FileWriteError;
use Blink\Exception\InvalidValue;
use Blink\Exception\JSONDecodeError;
use Blink\Exception\MissingParam;
use Blink\Exception\RuntimeError;
use Blink\Exception\UnserializeError;
use Blink\Response\APIResponse;

require_once "const.php";

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
	private $start;

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
function safeJSONParsing(String $json, String $path = "", bool $assoc = false) {
	// Temporary disable `NOTICE` error reporting
	// to try unserialize data without triggering `E_NOTICE`
	set_error_handler(null, 0);
	$json = json_decode($json, $assoc);
	restore_error_handler();

	if ($json === null)
		throw new JSONDecodeError($path, json_last_error_msg(), Array(
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
		$units = Array( "năm" => 31536000, "ngày" => 86400, "giờ" => 3600, "phút" => 60, "giây" => 1 );
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
 * @param	mixed	$value
 * @return	mixed	with proper utf-8 encoding
 */
function cleanUTF8($value) {
    if (is_null($value) || $value === "")
        return $value;

    if (is_string($value)) {
        if ((string)(int) $value === $value)
            return $value;

        // No null bytes expected in our data, so let"s remove it.
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
		throw new BaseException(-1, "cleanParam(): this function does not accept array or object!");

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
			throw new BaseException(-1, "validate(): unknown param type: $type", 400);
	}

	if (!$valid) {
		if ($throw)
			throw new InvalidValue($value, $type);

		return false;
	}

	return true;
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
function requiredParam(String $name, $type = TYPE_TEXT) {
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
function getHeader(String $name, $type = TYPE_TEXT, $default = null) {
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
 * @param	string	$ext		File extension
 * @param	string	$charset
 * @return	string|null
 */
function contentType(String $ext, String $charset = "utf-8") {
	$mimet = Array(
		"txt" => "text/plain",
		"htm" => "text/html",
		"html" => "text/html",
		"php" => "text/html",
		"css" => "text/css",
		"js" => "application/javascript",
		"json" => "application/json",
		"xml" => "application/xml",
		"swf" => "application/x-shockwave-flash",
		"crx" => "application/x-chrome-extension",
		"flv" => "video/x-flv",
		"log" => "text/x-log",
		"csv" => "text/csv",

		// images
		"png" => "image/png",
		"jpe" => "image/jpeg",
		"jpeg" => "image/jpeg",
		"jpg" => "image/jpeg",
		"gif" => "image/gif",
		"bmp" => "image/bmp",
		"ico" => "image/vnd.microsoft.icon",
		"tiff" => "image/tiff",
		"tif" => "image/tiff",
		"svg" => "image/svg+xml",
		"svgz" => "image/svg+xml",
		"webp" => "image/webp",

		// archives
		"zip" => "application/zip",
		"rar" => "application/x-rar-compressed",
		"exe" => "application/x-msdownload",
		"msi" => "application/x-msdownload",
		"cab" => "application/vnd.ms-cab-compressed",

		// audio/video
		"mp3" => "audio/mpeg",
		"qt" => "video/quicktime",
		"mov" => "video/quicktime",
		"mp4" => "video/mp4",
		"3gp" => "video/3gpp",
		"avi" => "video/x-msvideo",
		"wmv" => "video/x-ms-wmv",

		// adobe
		"pdf" => "application/pdf",
		"psd" => "image/vnd.adobe.photoshop",
		"ai" => "application/postscript",
		"eps" => "application/postscript",
		"ps" => "application/postscript",

		// ms office
		"doc" => "application/msword",
		"rtf" => "application/rtf",
		"xls" => "application/vnd.ms-excel",
		"ppt" => "application/vnd.ms-powerpoint",
		"docx" => "application/msword",
		"xlsx" => "application/vnd.ms-excel",
		"pptx" => "application/vnd.ms-powerpoint",

		// open office
		"odt" => "application/vnd.oasis.opendocument.text",
		"ods" => "application/vnd.oasis.opendocument.spreadsheet",
	);

	if (isset($mimet[$ext])) {
		header("Content-Type: ". $mimet[$ext] ."; charset=". $charset);
		return $mimet[$ext];
	} else
		return null;
}

function expireHeader($time) {
	header("Cache-Control: public, max-age=$time");
	header("Expires: " . gmdate("D, d M Y H:i:s \G\M\T", time() + $time));
	header_remove("pragma");
}

/**
 * Return new path relative to webserver"s
 * root path
 * 
 * @return	string
 */
function getRelativePath(String $fullPath, String $separator = "/", String $base = BASE_PATH) {
	if ($separator === "/") {
		$search = BASE_PATH;
		$subject = str_replace("\\", "/", $fullPath);
	} else {
		$search = preg_replace("/(\\\\|\/)/m", $separator, $base);
		$subject = preg_replace("/(\\\\|\/)/m", $separator, $fullPath);
	}

	return str_replace($search, "", $subject);
}

function header_set($name) {
	$name = strtolower($name);

	foreach (headers_list() as $item)
		if (strpos(strtolower($item), $name) >= 0)
			return true;
		
	return false;
}

function getClientIP() {
	return $_SERVER["REMOTE_ADDR"]
		?? $_SERVER["HTTP_CLIENT_IP"]
		?? getenv("HTTP_CLIENT_IP")
		?? getenv("HTTP_X_FORWARDED_FOR")
		?? getenv("HTTP_X_FORWARDED")
		?? getenv("HTTP_FORWARDED_FOR")
		?? getenv("HTTP_FORWARDED")
		?? getenv("REMOTE_ADDR")
		?? "UNKNOWN";
}

function stringify($subject) {
	$output = "";

	if (is_callable($subject)) {
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
			$output = Array();
	
			foreach ($subject as $key => $value)
				$output[] = "$key = " . htmlspecialchars($value);
	
			$output = implode(", ", $output);
			$output = "[ {$output} ]";
		}
	} else if (is_bool($subject)) {
		$output = $subject ? "true" : "false";
	} else if (is_numeric($subject)) {
		$output = (String) $subject;
	} else if ($subject === null) {
		$output = "[NULL]";
	} else {
		$output = (String) $output;
	}

	return $output;
}

/**
 * Return Human Readable Size
 * 
 * @param	int		$bytes		Size in byte
 * @return	string	Readable Size
 */
function convertSize(int $bytes) {
	$sizes = array("B", "KB", "MB", "GB", "TB");
	for ($i = 0; $bytes >= 1024 && $i < (count($sizes) -1); $bytes /= 1024, $i++);
	
	return (round($bytes, 2 ) . " " . $sizes[$i]);
}

class FileWithPriority {
	public String $path;
	public int $priority;

	public function __construct(String $path, int $priority) {
		$this -> path = $path;
		$this -> priority = $priority;
	}

	public function __serialize() {
        return Array(
			$this -> path,
			$this -> priority
		);
    }

    public function __unserialize(Array $data) {
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
	$list = Array();

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

	if (empty($cache -> getData())) {
		$data = globFilesPriority($pattern, $flags);
		$cache -> save($data);
		return $data;
	}

	return $cache -> getData();
}

/**
 * Redirect to target URL.
 *
 * @param	string|\URL		$url
 * @return	void
 */
function redirect($url) {
	if ($url instanceof \URL)
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
function getFiles(String $path, String $extension = "*") {
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
function renderSourceCode(String $file, int $line, int $count = 10) {
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
	$keywords = Array(	"try", "catch", "return", "public", "protected", "private", "static", "include_once",
						"include", "require_once", "require", "global", "if", "else", "use", "throw",
						"new", "\$this", "self", "echo", "print", "foreach", "for", "continue", "break" );
	$re = '/(^|[\t\n\(\! ])(' . implode("|", $keywords) . ')(?=[\t\n\(\{\:\; ])/mi';
	$content = preg_replace($re, '$1<span class="sc-keyword">$2</span>', $content);

	// Function regex
	$re = '/([\t\n\( ]|^)(function)(?=[\t\n\(\{ ])/mi';
	$content = preg_replace($re, '$1<span class="sc-function">$2</span>', $content);
		
	// Class name regex
	$re = '/(^| |\()([A-Z\\\\]{1}[a-zA-Z0-9\\\\]+)([\t\n\;\(\)\{\:\- ]|$)/m';
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
		$classes = Array( "line" );

		// Index start from 0, but file's line start from 1
		if ($i == $line - $from - 1)
			$classes[] = "current";

		$numHtml .= HTMLBuilder::div(Array( "class" => $classes ), $from + $i + 1);
		$lineHtml .= HTMLBuilder::div(Array( "class" => $classes ), "<span>{$code}</span>");
	}

	echo HTMLBuilder::startDIV(Array( "class" => "sourceCode" ));
	echo HTMLBuilder::span(Array( "class" => "nums" ), $numHtml);
	echo HTMLBuilder::build("code", Array( "class" => "lines" ), $lineHtml);
	echo HTMLBuilder::endDIV();
}

/**
 * Fast file get content with metric recording.
 * 
 * @param	string		$path		Path to file
 * @param	string		$default	Default value
 * @return	string|null				File content or default value if failed.
 */
function fileGet(String $path, $default = null): String|null {
	$metric = null;

	if (!file_exists($path))
		return $default;

	if (class_exists('\Blink\Metric\File'))
		$metric = new \Blink\Metric\File("r", "text", $path);
	
	$content = file_get_contents($path);

	if ($content === false) {
		$metric ?-> time(-1);
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
function filePut(String $path, $content): int|null {
	$metric = null;

	if (class_exists('\Blink\Metric\File'))
		$metric = new \Blink\Metric\File("w", "text", $path);
	
	$bytes = file_put_contents($path, $content);
	$metric ?-> time(($bytes === false) ? -1 : $bytes);
	return ($bytes === false) ? null : $bytes;
}

/**
 * Simple File Input/Output
 * 
 * @author	Belikhun
 * @version	2.1
 */
class FileIO {
	private $maxTry = 20;
	public $stream;
	public $path;

	public function __construct(
		String $path,
		mixed $defaultData = "",
		String $defaultType = TYPE_TEXT
	) {
		$this -> path = $path;

		if (!file_exists($path))
			$this -> write($defaultData, $defaultType, "x");
	}

	public function fos(String $path, String $mode) {
		$dirname = dirname($path);

		// Create parent folder if not exist yet.
		if (!is_dir($dirname))
			mkdir($dirname, 0777, true);

		$this -> stream = fopen($path, $mode);

		if (!$this -> stream) {
			$e = error_get_last();
			throw new BaseException(
				8,
				"FileIO -> fos(): [". $e["type"] ."]: "
					. $e["message"] . " tại "
					. $e["file"] . " dòng ". $e["line"],
				500,
				$e
			);
		}
	}

	public function fcs() {
		fclose($this -> stream);
	}

	/**
	 *
	 * Read file
	 * type: text/json/serialize
	 * 
	 * @param	string	$type	File data type
	 * @return	string|array|object|mixed
	 *
	 */
	public function read($type = TYPE_TEXT) {
		if (file_exists($this -> path)) {
			$tries = 0;
			
			while (!is_readable($this -> path)) {
				$tries++;

				if ($tries >= $this -> maxTry) {
					throw new BaseException(
						46,
						"FileIO -> read(): Read Timeout: Không có quyền đọc file "
							. basename($this -> path) ." sau $tries lần thử",
						500,
						Array( "path" => $this -> path )
					);
				}
				
				usleep(200000);
			}
		}

		if (class_exists("\Blink\Metric\File"))
			$metric = new \Blink\Metric\File("r", $type, $this -> path);

		$this -> fos($this -> path, "r");

		if (filesize($this -> path) > 0)
			$data = fread($this -> stream, filesize($this -> path));
		else
			$data = null;

		$this -> fcs();

		if (isset($metric))
			$metric -> time(!empty($data) ? mb_strlen($data, "utf-8") : -1);

		switch ($type) {
			case TYPE_JSON:
				return safeJSONParsing($data, $this -> path);

			case TYPE_JSON_ASSOC:
				return safeJSONParsing($data, $this -> path, true);

			case TYPE_SERIALIZED:
				// Temporary disable `NOTICE` error reporting
				// to try unserialize data without triggering `E_NOTICE`
				try {
					set_error_handler(null, 0);
					$data = (!empty($data)) ? unserialize($data) : false;
					restore_error_handler();
				} catch (Throwable $e) {
					// pass
				}

				if ($data === false || $data === serialize(false)) {
					$e = error_get_last();
					throw new UnserializeError($this -> path, $e["message"], $e);
				}

				return $data;
			
			default:
				return $data;
		}
	}

	/**
	 *
	 * Write data to file
	 * type: text/json/serialize
	 * 
	 * @param	string|array|object		$data		Data to write
	 * @param	string					$type		File data type
	 * @return
	 *
	 */
	public function write($data, String $type = TYPE_TEXT, String $mode = "w") {
		if (file_exists($this -> path)) {
			$tries = 0;
			
			while (!is_writable($this -> path)) {
				$tries++;

				if ($tries >= $this -> maxTry)
					throw new FileWriteError($this -> path, $tries);

				usleep(200000);
			}
		}

		if (class_exists("\Blink\Metric\File"))
			$metric = new \Blink\Metric\File($mode, $type, $this -> path);
		
		$this -> fos($this -> path, $mode);

		switch ($type) {
			case TYPE_JSON:
				$data = json_encode($data, JSON_PRETTY_PRINT);
				break;

			case TYPE_SERIALIZED:
				$data = serialize($data);
				break;
		}

		fwrite($this -> stream, $data);
		$this -> fcs();

		if (isset($metric))
			$metric -> time(mb_strlen($data, "utf-8"));

		return true;
	}
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
 * @param	int|float		$min		Minimum Random Number
 * @param	int|float		$max		Maximum Random Number
 * @param   bool			$toInt		To return an Integer Value
 * @return	int|float		Generated number
 */
function randBetween($min, $max, bool $toInt = true) {
	$rand = (float) (mt_rand() / mt_getrandmax());

	return $toInt
		? intval($rand * ($max - $min + 1) + $min)
		: ($rand * ($max - $min) + $min);
}

define("RAND_CHARSET_TEXT", "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789");
define("RAND_CHARSET_HEX", "0123456789abcdef");

/**
 * Generate Random String
 * @param	int			$len		Length of the randomized string
 * @param	string		$charset	Charset
 * @return	string		Generated String
 */
function randString(int $len = 16, String $charset = RAND_CHARSET_TEXT) {
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
function trimString(String $string, int $maxLen) {
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
 * Process backtrace data returned from {@link debug_backtrace()}
 * or {@link Exception::getTrace()}
 * 
 * @param	\Throwable|array	$data
 * @return	BacktraceFrame[]
 */
function processBacktrace($data, bool $forward = true) {
	global $ERROR_STACK;

	$exception = null;
	$frames = Array();

	if ($data instanceof \Throwable) {
		$exception = $data;
		$data = $exception -> getTrace();
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
				if (strlen($arg) > 5 && realpath($arg))
					$arg = getRelativePath($arg);
			} else if (is_object($arg)) {
				if ($arg instanceof \Throwable)
					$arg = [ get_class($arg), $arg -> getMessage() ];
				else if (method_exists($arg, "__toString"))
					$arg = [ get_class($arg), (string) $arg ];
				else
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
			$trace = new BacktraceFrame("[unknown]");
			$trace -> file = $file;
			$trace -> line = $exception -> getLine();
			$trace -> fault = true;
			array_unshift($frames, $trace);
		}
	}

	if (!empty($exception) && $forward) {
		// Merge forward.
		$merges = backtrace();
		$mcount = 0;
	
		foreach ($merges as $merge) {
			$check = $frames[$mcount];
	
			if (($merge -> file === $check -> file && $merge -> line === $check -> line)
				|| $merge -> function === $check -> function
			) {
				$frames[$mcount] -> file = $merge -> file ?: $check -> file;
				$frames[$mcount] -> line = $merge -> line ?: $check -> line;
				$frames[$mcount] -> function = $merge -> function ?: $check -> function;
				break;
			}
	
			if ($mcount === 0)
				array_unshift($frames, $merge);
			else
				array_splice($frames, $mcount, 0, [ $merge ] );
			
			$mcount += 1;
		}

		// Merge backward
		foreach (array_reverse($ERROR_STACK) as $e) {
			if ($e == $exception)
				continue;
	
			$pstacks = processBacktrace($e, false);
	
			// Add to current stack one by one.
			foreach ($pstacks as $stack) {
				$last = $frames[count($frames) - 1];
	
				if ($last -> file !== $stack -> file || $last -> line !== $stack -> line)
					$frames[] = $stack;
			}
		}
	}

	// Update fault points
	foreach ($ERROR_STACK as $e) {
		$file = getRelativePath($e -> getFile());
		$line = $e -> getLine();

		foreach ($frames as $frame) {
			if ($frame -> file === $file && $frame -> line === $line)
				$frame -> fault = true;
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
	String $description = "",
	int $status = 200,
	array|object $data = Array(),
	$hashData = false
) {
	$response = new APIResponse($code, $description, $data, $hashData);
	$response -> status($status);

	// Create a new error page instance!
	$instance = \Blink\ErrorPage\Instance::create($response -> output());
	$response -> set("report", $instance -> url());

	if (!defined("PAGE_TYPE"))
		define("PAGE_TYPE", "NORMAL");

	switch (strtoupper(PAGE_TYPE)) {
		case "NORMAL":
			if (!headers_sent()) {
				$response -> header("Output", "[{$response -> code}] {$response -> description}");
				$response -> serve(false);
			}


			if ($status >= 300 || $code !== 0)
				renderErrorPage($instance, headers_sent());

			break;
		
		case "API":
			$response -> header("Access-Control-Allow-Origin", "*");
			$response -> serve();
			break;

		default:
			print "<h1>Code $status</h1><p>$description</p>";
			break;
	}

	die();
}

function printException(Throwable $e) {
	$lines = Array();

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
