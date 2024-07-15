<?php

namespace Blink;

use CONFIG;

/**
 * Server.php
 * 
 * Class containing current Server information.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Server {
	/**
	 * Current host name. Will try to use `$_SERVER["HTTP_X_FORWARDED_HOST"]` when
	 * reverse proxy is enabled.
	 * 
	 * @var string
	 */
	public static string $HOST;

	/**
	 * Current request scheme. Will try to use `$_SERVER["HTTP_X_FORWARDED_PROTO"]` when
	 * reverse proxy is enabled.
	 * 
	 * @var string
	 */
	public static string $SCHEME;

	/**
	 * Name and revision of the information protocol via which the page was requested; e.g. `HTTP/1.0`;
	 * 
	 * @var string
	 */
	public static string $PROTOCOL;

	/**
	 * Current request method. Value are uppercased following HTTP verbs speficiation.
	 * See {@link https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods}
	 * 
	 * @var string
	 */
	public static string $METHOD;

	/**
	 * Server identification string, given in the headers when responding to requests.
	 * 
	 * @var string
	 */
	public static string $SOFTWARE;

	/**
	 * The timestamp of the start of the request, with microsecond precision.
	 * 
	 * @var float
	 */
	public static float $REQUEST_START;

	/**
	 * The client IP Address that created this request. Will try to use `$_SERVER["HTTP_X_FORWARDED_FOR"]` when
	 * reverse proxy is enabled.
	 * 
	 * @var string
	 */
	public static string $CLIENT_IP;

	public static function setup() {
		static::$HOST = first(array(
			$_SERVER["HTTP_HOST"] ?? null,
			$_SERVER["SERVER_NAME"] ?? null,
			getHeader("Host", TYPE_TEXT, ""),
			"127.0.0.1"
		));

		static::$SCHEME = first(array(
			$_SERVER["REQUEST_SCHEME"] ?? null,
			"http"
		));

		static::$PROTOCOL = $_SERVER["SERVER_PROTOCOL"];
		static::$METHOD = $_SERVER["REQUEST_METHOD"];
		static::$SOFTWARE = $_SERVER["SERVER_SOFTWARE"] ?? ("PHP " . phpversion());
		static::$REQUEST_START = $_SERVER["REQUEST_TIME_FLOAT"];

		static::$CLIENT_IP = first(array(
			$_SERVER["REMOTE_ADDR"] ?? null,
			$_SERVER["HTTP_CLIENT_IP"] ?? null,
			getenv("REMOTE_ADDR"),
			getenv("HTTP_CLIENT_IP"),
			"127.0.0.1"
		));

		// TODO: Check the request headers for forwarded information.
		// TODO: Following: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Forwarded
		if (CONFIG::$REVERSE_PROXY) {
			if (!empty($_SERVER["HTTP_X_FORWARDED_HOST"]))
				static::$HOST = $_SERVER["HTTP_X_FORWARDED_HOST"];

			if (!empty($_SERVER["HTTP_X_FORWARDED_PROTO"]))
				static::$SCHEME = $_SERVER["HTTP_X_FORWARDED_PROTO"];

			if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
				static::$CLIENT_IP = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}

		/**
		 * Host name, based on user's request.
		 * 
		 * @var	string
		 */
		define("HOST", static::$HOST);
	}
}
