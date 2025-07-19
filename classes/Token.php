<?php

namespace Blink;

use Blink\Exception\InvalidToken;
use Blink\Exception\TokenExpired;
use CONFIG;

/**
 * Represent an API token.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Token {
	/** @var int */
	public $id;
	
	/** @var string */
	public $token;

	/** @var int */
	public $created;
	
	/** @var int */
	public $expire;

	/** @var string */
	public $username;

	public function __construct(
		int $id,
		string $token,
		int $created,
		int $expire,
		string $username
	) {
		$this -> id = $id;
		$this -> token = $token;
		$this -> created = $created;
		$this -> expire = $expire;
		$this -> username = $username;
	}

	public function validate() {
		return ($this -> expire >= time());
	}

	public function renew() {
		global $DB;

		$this -> created = time();
		$this -> expire = $this -> created + CONFIG::$TOKEN_LIFETIME;

		$DB -> update("tokens", $this);
	}

	public static function getToken(string $token) {
		global $DB;
		$record = $DB -> record("tokens", array( "token" => $token ));

		if (empty($record))
			throw new InvalidToken();

		$token = static::processRecord($record);

		if (!$token -> validate())
			throw new TokenExpired();

		return $token;
	}

	/**
	 * Get usable token. Will always return a valid token.
	 * 
	 * @param	string	$username
	 * @return	Token
	 */
	public static function get(string $username) {
		global $DB;
		$record = $DB -> record("tokens", array( "username" => $username ));

		if (empty($record))
			return static::createToken($username);

		$token = static::processRecord($record);

		if (!$token -> validate())
			$token -> renew();

		return $token;
	}

	/**
	 * Create a new token for the specified user.
	 * 
	 * @param	string	$username
	 * @return	Token
	 */
	public static function createToken(string $username) {
		global $DB;
		
		$token = bin2hex(random_bytes(64));
		$created = time();
		$expire = $created + CONFIG::$TOKEN_LIFETIME;

		$id = $DB -> insert("tokens", array(
			"token" => $token,
			"created" => $created,
			"expire" => $expire,
			"username" => $username
		));

		return new static($id, $token, $created, $expire, $username);
	}

	/**
	 * Process token record from database.
	 * 
	 * @param	object	$record
	 * @return	Token
	 */
	public static function processRecord($record) {
		return new static(
			$record -> id,
			$record -> token,
			$record -> created,
			$record -> expire,
			$record -> username
		);
	}
}