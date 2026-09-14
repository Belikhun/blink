<?php

namespace Blink\Http\Response;

use Throwable;
use Blink\ErrorPage\Inspector;
use Blink\Exception\BaseException;
use Blink\Session;

/**
 * Build the payload shared by {@see APIResponse} and the error page.
 *
 * One place decides what an exception leaks to the client: with debugging off,
 * only the data a blink exception chose to carry gets through, and the stack
 * trace, caller and everything else stays on the server.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ResponsePayloadBuilder {
	public static function build(
		int $code,
		string $description,
		int $status,
		array|object|null $data = null,
		?string $hash = null
	): array {
		global $RUNTIME;

		$exceptionData = null;
		$caller = "hidden";
		$details = null;
		$output = null;

		if ($data instanceof Throwable) {
			$exception = $data;

			if ($exception instanceof BaseException)
				$details = $exception -> details;

			// Built in full whatever the debug setting: this payload is also
			// what the stored report is made from. {@see redact()} decides what
			// of it the client sees.
			$exceptionData = Inspector::buildExceptionData($exception);

			if (!empty($exceptionData["stacktrace"]))
				$caller = $exceptionData["stacktrace"][0] -> getCallString();

			$data = ($exception instanceof BaseException)
				? $exception -> data
				: null;

			// Whatever the request managed to print before it failed. Warnings
			// and half-rendered pages land here rather than in front of the
			// error page, and the report keeps them.
			$output = "";
			while (ob_get_level())
				$output .= ob_get_clean();
		}

		return array(
			"code" => $code,
			"status" => $status,
			"description" => str_replace(BASE_PATH, "", $description),
			"details" => $details,
			"caller" => "{$caller}()",
			"user" => class_exists(Session::class, false)
				? Session::$username
				: null,
			"data" => $data,
			"hash" => $hash,
			"runtime" => !empty($RUNTIME) ? $RUNTIME -> stop() : 0,
			"output" => $output,
			"exception" => $exceptionData
		);
	}

	/**
	 * The payload as the client may see it.
	 *
	 * With debugging off, the exception section, the caller and the captured
	 * output stay on the server. They are still in the stored report.
	 */
	public static function redact(array $payload): array {
		if (\Blink::debuggingEnabled())
			return $payload;

		$payload["exception"] = null;
		$payload["caller"] = "hidden()";
		$payload["output"] = null;

		return $payload;
	}
}
