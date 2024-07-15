<?php

namespace Blink;
use Blink\Exception\NotLoggedIn;

/**
 * Session.php
 * 
 * Represent current active session.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

class Session {
	const METHOD_SESSION = "session";

	const METHOD_TOKEN = "token";

	/**
	 * Session lifespan. Default to 1 day.
	 * 
	 * @var	int
	 */
	public static $lifetime;

	/**
	 * Current active username in session.
	 * 
	 * @var	string
	 */
	public static $username = null;

	/**
	 * Current logged-in user.
	 * 
	 * @var \User
	 */
	public static $user = null;

	/**
	 * Store logout token user need to perform
	 * logout.
	 * 
	 * @var string
	 */
	public static $logoutToken = null;

	/**
	 * Current authentication method.
	 * Can be `Session::METHOD_SESSION` or `Session::METHOD_TOKEN`
	 * 
	 * @var ?string
	 */
	public static ?string $method = null;

	/**
	 * Authenticated token.
	 * Only populated when using token authentication method.
	 * 
	 * @var ?string
	 */
	public static ?Token $token = null;

	public static function start(string $sessionID = null) {
		session_name("Session");

		if (!empty($sessionID))
			session_id($sessionID);

		if (session_status() !== PHP_SESSION_ACTIVE) {
			ini_set("session.gc_maxlifetime", static::$lifetime);
			session_cache_expire(static::$lifetime / 60);
			session_set_cookie_params(array(
				"lifetime" => static::$lifetime
			));

			session_start();
			setcookie(session_name(), session_id(), time() + static::$lifetime, "/");
		}

		if (empty($_SESSION["username"])) {
			$_SESSION["username"] = null;
			return;
		}

		static::$username = $_SESSION["username"];
		static::$user = $_SESSION["user"];
		static::$logoutToken = $_SESSION["logoutToken"];
		static::$method = static::METHOD_SESSION;
		static::$token = null;
	}

	/**
	 * Start session using token string.
	 * 
	 * @param	string	$token
	 */
	public static function token(string $token) {
		$token = Token::getToken($token);

		static::$username = $token -> username;
		static::$user = \User::getByUsername($token -> username);
		static::$logoutToken = null;
		static::$method = static::METHOD_TOKEN;
		static::$token = $token;
	}

	/**
	 * Create access token for this user.
	 * 
	 * @param	\User	$user
	 * @return	Token
	 */
	public static function createToken(\User $user) {
		static::$username = $user -> username;
		static::$user = $user;
		static::$logoutToken = null;
		
		$token = Token::createToken($user -> username);
		static::$method = static::METHOD_TOKEN;
		static::$token = $token;

		return $token;
	}

	public static function completeLogin(\User $user) {
		$_SESSION["username"] = $user -> username;
		$_SESSION["user"] = $user;
		$_SESSION["logoutToken"] = bin2hex(random_bytes(12));
		static::$user = $user;

		session_regenerate_id();
	}

	public static function terminate() {
		unset($_SESSION["username"]);
		unset($_SESSION["user"]);
		unset($_SESSION["logoutToken"]);
		static::$username = null;
		static::$user = null;
		static::$logoutToken = null;
		static::$method = null;
		static::$token = null;

		session_destroy();
	}

	public static function loggedIn() {
		if (session_status() !== PHP_SESSION_NONE && !empty(static::$username))
			return true;
		else
			return false;
	}

	public static function requireLogin() {
		if (!static::loggedIn())
			throw new NotLoggedIn();
	}
}

Session::$lifetime = &\CONFIG::$SESSION_LIFETIME;
