<?php

namespace Blink\ErrorPage;

use Throwable;
use Blink\Exception\BaseException;
use Blink\Exception\FatalError;
use Blink\Exception\RuntimeError;
use function Blink\getRelativePath;
use function Blink\isSequential;

/**
 * Turn a throwable, or a raw backtrace, into {@see Frame} objects ready for
 * rendering.
 *
 * Three steps: a frame for every trace entry; a synthetic frame in front of
 * them built from the exception's own file and line, because
 * `Throwable::getTrace()` never includes the throw site itself; then the
 * previous-exception chain merged in, with frames that share a source
 * location folded together.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Inspector {
	/**
	 * The current execution backtrace as frames.
	 *
	 * @return	Frame[]
	 */
	public static function backtrace(int $limit = 0): array {
		return static::process(debug_backtrace(0, $limit));
	}

	/**
	 * The exception section shared by the error page and the API response.
	 *
	 * @return	array{class: string, file: string, line: int, data: mixed, stacktrace: Frame[]}
	 */
	public static function buildExceptionData(Throwable $exception): array {
		return array(
			"class" => get_class($exception),
			"file" => getRelativePath($exception -> getFile()),
			"line" => $exception -> getLine(),
			"data" => ($exception instanceof BaseException) ? $exception -> data : null,
			"stacktrace" => static::process($exception)
		);
	}

	/**
	 * Process trace data from {@see debug_backtrace()} or
	 * {@see Throwable::getTrace()}.
	 *
	 * @param	Throwable|array		$data
	 * @param	bool				$merges	Whether to fold the previous-exception
	 * 										chain and the live backtrace in.
	 * @return	Frame[]
	 */
	public static function process($data, bool $merges = true): array {
		$exception = null;
		$frames = array();

		if ($data instanceof Throwable) {
			$exception = $data;
			$data = static::resolveTrace($exception);
		}

		foreach ($data as $item)
			$frames[] = static::frameFromTrace($item);

		// A RuntimeError's file and line are the handler's arguments, and the
		// trace already starts there.
		if (!empty($exception) && !($exception instanceof RuntimeError)) {
			$file = getRelativePath($exception -> getFile());
			$line = $exception -> getLine();

			if ($file && $line && (empty($frames) || ($frames[0] -> file !== $file || $frames[0] -> line !== $line))) {
				$frame = new Frame(get_class($exception));
				$frame -> file = $file;
				$frame -> line = $line;
				$frame -> fault = true;
				array_unshift($frames, $frame);
			}
		}

		if (!empty($exception) && $merges) {
			for ($previous = $exception -> getPrevious(); $previous !== null; $previous = $previous -> getPrevious())
				$frames = static::merge($frames, static::process($previous, false));

			$frames = static::merge(static::liveFrames($frames), $frames);
			$frames = static::reduce($frames);

			// After merging, so a fault flag survives a merge collision.
			for ($e = $exception; $e !== null; $e = $e -> getPrevious()) {
				$file = getRelativePath($e -> getFile());
				$line = $e -> getLine();

				foreach ($frames as $frame) {
					if ($frame -> matches($file, $line))
						$frame -> fault = true;
				}
			}
		}

		return $frames;
	}

	/**
	 * The live backtrace, minus the error-handling frames at its top.
	 *
	 * Taken from inside the handler, the live stack starts with this
	 * inspector, the payload builder and the handler itself, none of which is
	 * where the error happened. Everything above the first frame the
	 * exception's own trace also has is dropped, so the merge only fills in
	 * the tail the two share.
	 *
	 * @param	Frame[]	$frames		The exception's frames.
	 * @return	Frame[]
	 */
	protected static function liveFrames(array $frames): array {
		$known = array();

		foreach ($frames as $frame)
			$known[$frame -> hash()] = true;

		$live = static::backtrace();

		foreach ($live as $i => $frame) {
			if (isset($known[$frame -> hash()]))
				return array_slice($live, $i);
		}

		return array();
	}

	/**
	 * The raw trace entries of an exception.
	 *
	 * A fatal error only traces back to the shutdown handler. When Xdebug is
	 * loaded, its function stack still holds the calls that led to the fatal,
	 * so that is used instead.
	 */
	protected static function resolveTrace(Throwable $exception): array {
		$native = ($exception instanceof BaseException)
			? $exception -> trace()
			: $exception -> getTrace();

		if (!static::shouldUseXdebugTrace($exception))
			return $native;

		$xdebug = static::reduceXdebugStack(
			xdebug_get_function_stack(),
			debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
		);

		return !empty($xdebug)
			? $xdebug
			: $native;
	}

	protected static function shouldUseXdebugTrace(Throwable $exception): bool {
		if (!($exception instanceof FatalError))
			return false;

		if (!static::isFatalLevel((int) $exception -> code))
			return false;

		return extension_loaded("xdebug") && function_exists("xdebug_get_function_stack");
	}

	/**
	 * Whether a PHP error level is one the engine cannot continue from.
	 * `E_RECOVERABLE_ERROR` and `E_USER_ERROR` are deliberately left out: the
	 * error handler sees those, so their trace is already complete.
	 */
	protected static function isFatalLevel(int $level): bool {
		$fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING;
		return ($level & $fatal) > 0;
	}

	/**
	 * Turn a raw {@see xdebug_get_function_stack()} stack into newest-first
	 * entries shaped like {@see debug_backtrace()} output.
	 *
	 * Xdebug lists oldest first, so the stack is reversed. Its leading frames
	 * are then the error-handling machinery, from the shutdown handler down to
	 * this inspector; diffing keys against a live backtrace strips exactly
	 * those and leaves the application frames.
	 *
	 * @param	array	$xdebugStack	Raw oldest-first Xdebug stack.
	 * @param	array	$handlerTrace	Live backtrace measuring handler depth.
	 */
	protected static function reduceXdebugStack(array $xdebugStack, array $handlerTrace): array {
		$stack = array_reverse($xdebugStack);
		$stack = array_diff_key($stack, $handlerTrace);

		return array_values(array_map([ static::class, "normalizeXdebugFrame" ], $stack));
	}

	/**
	 * Xdebug names its keys differently (`params`, and `type` as static or
	 * dynamic); this maps one entry to the shape {@see frameFromTrace()} reads.
	 */
	protected static function normalizeXdebugFrame(array $frame): array {
		$normalized = array(
			"function" => $frame["function"] ?? "[unknown]",
			"file" => $frame["file"] ?? null,
			"line" => $frame["line"] ?? null
		);

		if (!empty($frame["class"]))
			$normalized["class"] = $frame["class"];

		if (isset($frame["type"]))
			$normalized["type"] = ($frame["type"] === "static") ? "::" : "->";

		if (isset($frame["params"]) && is_array($frame["params"]))
			$normalized["args"] = array_values($frame["params"]);

		return $normalized;
	}

	protected static function frameFromTrace(array $item): Frame {
		$frame = new Frame($item["function"] ?? "[unknown]");

		foreach ([ "file", "line", "class", "type" ] as $key) {
			if (empty($item[$key]))
				continue;

			if ($key === "file")
				$item[$key] = getRelativePath($item[$key]);

			$frame -> {$key} = $item[$key];
		}

		if (empty($item["args"]))
			return $frame;

		foreach ($item["args"] as $arg)
			$frame -> args[] = static::stringifyArg($arg);

		return $frame;
	}

	/**
	 * A display-safe scalar for a trace argument, or a `[class, value]` pair
	 * for an object that can describe itself.
	 */
	protected static function stringifyArg($arg) {
		if (is_string($arg)) {
			if (strlen($arg) > 5 && (str_contains($arg, ".php") || realpath($arg)))
				$arg = getRelativePath($arg);

			if (strlen($arg) > 123)
				$arg = substr($arg, 0, 120) . "...";

			return $arg;
		}

		if (is_object($arg)) {
			if ($arg instanceof Throwable) {
				$value = $arg -> getMessage();
				if (str_contains($value, ".php"))
					$value = getRelativePath($value);

				return [ get_class($arg), $value ];
			}

			if (method_exists($arg, "__toString")) {
				$value = (string) $arg;
				if (str_contains($value, ".php"))
					$value = getRelativePath($value);

				return [ get_class($arg), $value ];
			}

			return get_class($arg);
		}

		if (is_bool($arg))
			return $arg ? "true" : "false";

		if (is_array($arg)) {
			$count = count($arg);

			if (isSequential($arg)) {
				return $count > 0
					? "[ ...({$count})... ]"
					: "[]";
			}

			return $count > 0
				? "{ ...({$count})... }"
				: "{}";
		}

		if ($arg === null)
			return "[NULL]";

		return $arg;
	}

	/**
	 * Merge frames into a target set. A frame already present by source
	 * location ({@see Frame::hash()}) is enriched with whatever the incoming
	 * one knows that it does not; the rest are spliced in where they belong.
	 *
	 * @param	Frame[]	$from
	 * @param	Frame[]	$target
	 * @return	Frame[]
	 */
	protected static function merge(array $from, array $target, bool $reverse = false): array {
		$insert = 0;

		if ($reverse)
			$target = array_reverse($target);

		foreach ($from as $merge) {
			foreach ($target as $i => &$check) {
				if ($merge -> hash() === $check -> hash()) {
					if (!empty($merge -> file))
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

			array_splice($target, $insert - ($reverse ? 1 : 0), 0, [ $merge ]);
			$insert += 1;
		}

		return $reverse
			? array_reverse($target)
			: $target;
	}

	/**
	 * Collapse consecutive frames that describe the same call, keeping the
	 * richer of the two.
	 *
	 * Two frames collapse when they share a source location, or share a
	 * function name without sitting at conflicting locations. The location
	 * guard keeps the distinct throw sites of two chained exceptions of the
	 * same class apart.
	 *
	 * @param	Frame[]		$frames
	 * @return	Frame[]
	 */
	protected static function reduce(array $frames): array {
		for ($i = 0; $i < count($frames) - 1; $i++) {
			$frame = &$frames[$i];
			$next = $frames[$i + 1];

			$conflictingLocation = !empty($frame -> file) && !empty($next -> file)
				&& !$frame -> matches($next -> file, $next -> line);

			if (!$conflictingLocation && ($frame -> hash() === $next -> hash() || $frame -> function === $next -> function)) {
				if (!empty($next -> file))
					$frame -> file = $next -> file;

				if (!empty($next -> line) && $next -> line > 0)
					$frame -> line = $next -> line;

				$frame -> function = $next -> function ?: $frame -> function;

				if (empty($frame -> args) && !empty($next -> args))
					$frame -> args = $next -> args;

				array_splice($frames, $i + 1, 1);
			}
		}

		// The reference would otherwise alias the last element on the next loop.
		unset($frame);

		return $frames;
	}
}
