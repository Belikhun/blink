<?php

namespace Blink\ErrorPage;

use Throwable;
use Blink\ErrorPage;
use Blink\Http\Response\ResponsePayloadBuilder;
use function Blink\getHeader;
use function Blink\renderErrorPage;

/**
 * Turn a throwable into a stored report and a response the client can use.
 *
 * The error handlers hand everything to {@see handleThrowable()}. Which shape
 * the client gets, the HTML page or the JSON payload, follows its `Accept`
 * header.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ResponseAdapter {
	const TYPE_HTML = "html";
	const TYPE_JSON = "json";

	/**
	 * Report a throwable and respond with it.
	 *
	 * @param	bool	$terminate	Whether to end the script once the response is
	 * 								out. The shutdown handler passes false: an
	 * 								exit inside a shutdown function ends that
	 * 								function, and the steps after the report
	 * 								would never run.
	 */
	public static function handleThrowable(
		Throwable $throwable,
		int $code,
		string $description,
		int $status = 500,
		bool $terminate = true
	): void {
		$payload = ResponsePayloadBuilder::build($code, $description, $status, $throwable);
		$page = ErrorPage::fromPayload($payload) -> save();
		static::serve($payload, $page, $terminate);
	}

	public static function serve(array $payload, ErrorPage $page, bool $terminate = true): void {
		$payload["report"] = (string) $page -> url();
		$code = (int) ($payload["code"] ?? 0);
		$status = (int) ($payload["status"] ?? 500);
		$errored = ($status >= 300 || $code !== 0);

		switch (static::negotiate()) {
			case static::TYPE_JSON: {
				if (!headers_sent()) {
					http_response_code($status);
					header("Content-Type: application/json; charset=utf-8", true);
					header("Access-Control-Allow-Origin: *", true);
					header("Report-ID: {$page -> id}", true);
				}

				echo json_encode(ResponsePayloadBuilder::redact($payload), JSON_PRETTY_PRINT);
				break;
			}

			default: {
				if (!headers_sent()) {
					http_response_code($status);
					header("Content-Type: text/html; charset=utf-8", true);
					header("Output: {$code}", true);
					header("Report-ID: {$page -> id}", true);
				}

				if ($errored)
					renderErrorPage($page, headers_sent(), $terminate);

				break;
			}
		}

		if ($terminate)
			exit;
	}

	/**
	 * Which shape the client asked for. HTML unless JSON is named first.
	 */
	protected static function negotiate(): string {
		if (BLINK_IS_CLI)
			return static::TYPE_HTML;

		foreach (explode(",", getHeader("Accept", TYPE_TEXT, "")) as $accept) {
			$accept = trim($accept);

			if (str_starts_with($accept, "text/html"))
				return static::TYPE_HTML;

			if (str_starts_with($accept, "application/json"))
				return static::TYPE_JSON;
		}

		return static::TYPE_HTML;
	}
}
