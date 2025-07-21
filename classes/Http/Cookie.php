<?php

namespace Blink\Http;

use Blink\Exception\CodingError;

/**
 * Represent a single cookie 🍪.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Cookie {
	/**
	 * The browser sends the cookie only for same-site requests,
	 * that is, requests originating from the same site that set
	 * the cookie. If a request originates from a different domain
	 * or scheme (even with the same domain), no cookies with the
	 * `SameSite=Strict` attribute are sent.
	 */
	const SAMESITE_STRICT = "Strict";

	/**
	 * The cookie is not sent on cross-site requests, such as on
	 * requests to load images or frames, but is sent when a user
	 * is navigating to the origin site from an external site
	 * (for example, when following a link). This is the default
	 * behavior if the `SameSite` attribute is not specified.
	 */
	const SAMESITE_LAX = "Lax";

	/**
	 * The browser sends the cookie with both cross-site and
	 * same-site requests. The Secure attribute must also be set
	 * when setting this value, like so `SameSite=None; Secure`.
	 */
	const SAMESITE_NONE = "None";

	public string $name;
	public string $value;
	public array $attributes = array();

	public function __construct(string $name, string $value) {
		$this -> name = $name;
		$this -> value = $value;
	}

	/**
	 * Defines the host to which the cookie will be sent.
	 *
	 * Only the current domain can be set as the value,
	 * or a domain of a higher order, unless it is a public suffix.
	 * Setting the domain will make the cookie available to it,
	 * as well as to all its subdomains.
	 *
	 * If omitted, this attribute defaults to the host of the
	 * current document URL, not including subdomains.
	 *
	 * Multiple host/domain values are not allowed, but if a
	 * domain is specified, then subdomains are always included.
	 *
	 * @param	string	$host	Host name. Set to null or empty
	 * 		string to remove this attribute.
	 */
	public function domain(?string $host) {
		if (empty($host))
			unset($this -> attributes["Domain"]);
		else
			$this -> attributes["Domain"] = $host;

		return $this;
	}

	/**
	 * Indicates the maximum lifetime of the cookie as
	 * an HTTP-date timestamp.
	 *
	 * If unspecified, the cookie becomes a session cookie.
	 * A session finishes when the client shuts down, after
	 * which the session cookie is removed.
	 *
	 * @param	int		$timestamp	The date this cookie will
	 * 		be expired. Set to negative number to remove this attribute.
	 */
	public function expires(int $timestamp) {
		if ($timestamp < 0)
			unset($this -> attributes["Expires"]);
		else
			$this -> attributes["Expires"] = gmdate("D, d M Y H:i:s T", $timestamp);

		return $this;
	}

	/**
	 * Forbids JavaScript from accessing the cookie.
	 *
	 * @param	bool	$enabled	Enable this attribute?
	 */
	public function httpOnly(bool $enabled = true) {
		if (!$enabled)
			unset($this -> attributes["HttpOnly"]);
		else
			$this -> attributes["HttpOnly"] = true;

		return $this;
	}

	/**
	 * Indicates the number of seconds until the cookie expires.
	 * A zero or negative number will expire the cookie immediately.
	 *
	 * If both Expires and Max-Age are set, Max-Age has precedence.
	 *
	 * @param	int		$amount		Seconds until this cookie expire.
	 */
	public function maxAge(int $amount) {
		if ($amount < 0)
			unset($this -> attributes["Max-Age"]);
		else
			$this -> attributes["Max-Age"] = $amount;

		return $this;
	}

	/**
	 * Indicates that the cookie should be stored using partitioned storage.
	 *
	 * See {@link https://developer.mozilla.org/en-US/docs/Web/Privacy/Partitioned_cookies Cookies Having Independent Partitioned State (CHIPS)} for more details.
	 *
	 * @param	bool	$enabled	Enable this attribute?
	 */
	public function partitioned(bool $enabled = true) {
		if (!$enabled)
			unset($this -> attributes["Partitioned"]);
		else
			$this -> attributes["Partitioned"] = true;

		return $this;
	}

	/**
	 * Indicates the path that must exist in the requested URL
	 * for the browser to send the `Cookie` header.
	 *
	 * The forward slash (`/`) character is interpreted as a
	 * directory separator, and subdirectories are matched as well.
	 *
	 * @param	string	$path	Path. Set to null or empty
	 * 		string to remove this attribute.
	 */
	public function path(?string $path) {
		if (empty($path))
			unset($this -> attributes["Path"]);
		else
			$this -> attributes["Path"] = $path;

		return $this;
	}

	/**
	 * Controls whether or not a cookie is sent with cross-site
	 * requests, providing some protection against cross-site
	 * request forgery attacks (CSRF).
	 *
	 * The possible attribute values are:
	 *
	 * * {@see Blink\Http\Request\Cookie::SAMESITE_STRICT}: The browser sends the cookie only for same-site requests
	 * * {@see Blink\Http\Request\Cookie::SAMESITE_LAX}: The cookie is not sent on cross-site requests
	 * * {@see Blink\Http\Request\Cookie::SAMESITE_NONE}: The browser sends the cookie with both cross-site and same-site requests
	 */
	public function sameSite(?string $value) {
		if (!in_array($value, [ static::SAMESITE_STRICT, static::SAMESITE_LAX, static::SAMESITE_NONE, null ]))
			throw new CodingError("Cookie -> sameSite(): \"{$value}\" is not a valid value!");

		if (empty($value))
			unset($this -> attributes["SameSite"]);
		else
			$this -> attributes["SameSite"] = $value;

		return $this;
	}

	/**
	 * Indicates that the cookie is sent to the server only
	 * when a request is made with the https: scheme (except
	 * on localhost), and therefore, is more resistant to
	 * man-in-the-middle attacks.
	 *
	 * @param	bool	$enabled	Enable this attribute?
	 */
	public function secure(bool $enabled = true) {
		if (!$enabled)
			unset($this -> attributes["Secure"]);
		else
			$this -> attributes["Secure"] = true;

		return $this;
	}

	/**
	 * Build this cookie to a string to be ready to sent as
	 * `Set-Cookie` value in response header.
	 * @return	string
	 */
	public function build(): string {
		$attributes = array( "{$this -> name}={$this -> value}" );

		foreach ($this -> attributes as $name => $value) {
			if ($value === true || empty($value)) {
				$attributes[] = $name;
				continue;
			}

			if ($value === false)
				continue;

			$attributes[] = "{$name}={$value}";
		}

		return implode("; ", $attributes);
	}

	public function __toString() {
		return $this -> build();
	}
}
