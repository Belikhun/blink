<?php

namespace Blink\ErrorPage;

use Blink\HtmlWriter;
use function Blink\fileGet;
use function Blink\randString;

/**
 * One execution frame of a stack trace.
 *
 * Carries the raw trace data (file, line, class, type, function, args) plus
 * the fault flag the error page uses, and renders its own source snippet.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Frame {
	public ?string $file = null;
	public int $line = -1;
	public ?string $class = null;
	public ?string $type = null;
	public string $function;
	public array $args = array();

	/**
	 * Whether this frame is where the error was thrown.
	 */
	public bool $fault = false;

	private ?string $id = null;
	private ?string $fullpath = null;
	private ?string $hash = null;

	/**
	 * File contents already read, keyed by full path. Several frames usually
	 * point into the same file.
	 *
	 * @var array<string, ?string>
	 */
	private static array $fileContentsCache = array();

	public function __construct(string $function = "[unknown]", bool $fault = false) {
		$this -> function = $function;
		$this -> fault = $fault;
	}

	public function getFullPath(): ?string {
		if (empty($this -> file))
			return null;

		if (empty($this -> fullpath)) {
			$file = str_replace("\\", "/", $this -> file);

			$this -> fullpath = ($file[0] === "/" && !str_starts_with($file, BASE_PATH))
				? BASE_PATH . $file
				: $file;
		}

		return $this -> fullpath;
	}

	/**
	 * Whether this frame's file sits under a directory. Both sides are
	 * compared with forward slashes, since a Windows path can arrive with
	 * either.
	 */
	protected function isUnder(string $root): bool {
		return str_starts_with($this -> getFullPath(), str_replace("\\", "/", $root));
	}

	public function getCallString(): string {
		if (!empty($this -> class)) {
			return ($this -> type === "::")
				? "{$this -> class}::{$this -> function}"
				: "{$this -> class} -> {$this -> function}";
		}

		return $this -> function;
	}

	/**
	 * A random id for this frame, minted on first use. The page uses it to
	 * pair a frame entry with its source view.
	 */
	public function getID(): string {
		if (empty($this -> id))
			$this -> id = randString(8, RAND_CHARSET_HEX);

		return $this -> id;
	}

	public function hash(): string {
		if (!empty($this -> hash))
			return $this -> hash;

		$this -> hash = md5(($this -> file ?: "file") . ($this -> line ?: "0"));
		return $this -> hash;
	}

	/**
	 * Whether this frame is inside blink itself.
	 */
	public function isBlink(): bool {
		if (!empty($this -> file))
			return $this -> isUnder(CORE_ROOT);

		if (!empty($this -> class))
			return str_starts_with($this -> class, "Blink");

		return false;
	}

	/**
	 * Whether this frame is inside a composer package.
	 */
	public function isVendor(): bool {
		if (!empty($this -> file))
			return $this -> isUnder(VENDOR_ROOT);

		return false;
	}

	/**
	 * Whether this frame points at the same source location as another. Two
	 * frames are equal when they share a non-empty file and line, which is
	 * what merging chained exception traces dedupes on.
	 */
	public function equals(Frame $other): bool {
		return $this -> matches($other -> file, $other -> line);
	}

	public function matches(?string $file, int $line): bool {
		if (empty($this -> file) || empty($file))
			return false;

		return $this -> file === $file && $this -> line === $line;
	}

	public function getFileContents(): ?string {
		$path = $this -> getFullPath();

		if (empty($path) || !file_exists($path))
			return null;

		if (!array_key_exists($path, static::$fileContentsCache))
			static::$fileContentsCache[$path] = fileGet($path);

		return static::$fileContentsCache[$path];
	}

	/**
	 * A slice of this frame's source lines.
	 *
	 * The array is 0-indexed and a slice keeps the original keys, so the
	 * 1-based line number of an entry is `key + 1`.
	 *
	 * @param	int		$start	0-based offset of the first line.
	 * @param	?int	$length	Number of lines, or null for the whole file.
	 */
	public function getFileLines(int $start = 0, ?int $length = null): ?array {
		$contents = $this -> getFileContents();

		if ($contents === null)
			return null;

		$lines = explode("\n", $contents);

		if ($length === null)
			return $lines;

		if ($start < 0)
			$start = 0;

		if ($length <= 0)
			return array();

		return array_slice($lines, $start, $length, true);
	}

	/**
	 * This frame's source snippet as highlighted HTML.
	 *
	 * @param	int		$count	Lines to show around the frame's line.
	 */
	public function renderSourceCode(int $count = 27): string {
		return static::highlight($this -> getFullPath(), $this -> line, $count);
	}

	/**
	 * A syntax-highlighted window of `$count` lines centered on `$line`, as far
	 * as the file bounds allow.
	 *
	 * @param	int		$line	1-based line to center on.
	 */
	public static function highlight(?string $file, int $line, int $count = 10): string {
		if (empty($file) || !file_exists($file))
			return HtmlWriter::code(array(), "File does not exist: {$file}");

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

		$lines = array_slice($lines, $from, $to - $from + 1);
		$content = static::tokenize(implode("\n", $lines));

		$lines = explode("\n", $content);
		$numHtml = "";
		$lineHtml = "";

		for ($i = 0; $i < count($lines); $i++) {
			$code = trim($lines[$i], "\n\r");
			$classes = array( "line" );

			// The array starts at 0, the file's lines at 1.
			if ($i == $line - $from - 1)
				$classes[] = "current";

			$numHtml .= HtmlWriter::div(array( "class" => $classes ), $from + $i + 1);
			$lineHtml .= HtmlWriter::div(array( "class" => $classes ), "<span>{$code}</span>");
		}

		$html = HtmlWriter::startDIV(array( "class" => "sourceCode" ));
		$html .= HtmlWriter::span(array( "class" => "nums" ), $numHtml);
		$html .= HtmlWriter::build("code", array( "class" => "lines" ), $lineHtml);
		$html .= HtmlWriter::endDIV();

		return $html;
	}

	/**
	 * Naive PHP syntax highlighting over an HTML-escaped source chunk.
	 */
	protected static function tokenize(string $content): string {
		$keywords = array(	"try", "catch", "return", "public", "protected", "private", "static", "include_once",
							"include", "require_once", "require", "global", "if", "else", "use", "throw",
							"new", "\$this", "self", "echo", "print", "foreach", "for", "continue", "break", "instanceof",
							"default", "while", "switch", "case", "match", "class", "extends", "implement", "parent",
							"namespace" );
		$re = '/(^|[\t\n\(\! ])(' . implode("|", $keywords) . ')(?=[\t\n\(\{\:\; ])/mi';
		$content = preg_replace($re, '$1<span class="sc-keyword">$2</span>', $content);

		$re = '/([\t\n\( ]|^)(function)(?=[\t\n\(\{ ])/mi';
		$content = preg_replace($re, '$1<span class="sc-function">$2</span>', $content);

		$re = '/(^| |\(|\t)([A-Z\\\\]{1}[a-zA-Z0-9\\\\]+)([\t\n\;\(\)\{\:\- ]|$)/m';
		$content = preg_replace($re, '$1<span class="sc-class">$2</span>$3', $content);

		$re = '/(&quot;(.*)&quot;|\'(.*)\')/mU';
		$content = preg_replace($re, '<span class="sc-string">$1</span>', $content);

		$re = '/(\$[a-zA-Z0-9_]+)/m';
		$content = preg_replace($re, '<span class="sc-variable">$1</span>', $content);

		$re = '/([a-zA-Z0-9_]+)(\()/m';
		$content = preg_replace($re, '<span class="sc-function-name">$1</span>$2', $content);

		$re = '/([\t ]|^)(\/\/|\*|\/\*\*)(.*)$/m';
		$content = preg_replace($re, '$1<span class="sc-comment">$2$3</span>', $content);

		$content = str_replace("&lt;?php", '<span class="sc-meta">&lt;?php</span>', $content);
		$content = str_replace("?&gt;", '<span class="sc-meta">?&gt;</span>', $content);

		return $content;
	}

	public function __serialize(): array {
		return array(
			"file" => $this -> file,
			"line" => $this -> line,
			"class" => $this -> class,
			"type" => $this -> type,
			"function" => $this -> function,
			"args" => $this -> args,
			"fault" => $this -> fault
		);
	}

	public function __unserialize(array $data): void {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}
