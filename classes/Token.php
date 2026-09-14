<?php

namespace Blink;

use Blink\Exception\InvalidToken;
use Blink\Exception\TokenExpired;
use CONFIG;

/**
 * An API access token.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * @extends		Model<Token>
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Token extends Model {
	public static string $table = "tokens";

	public static $fillables = array(
		"id",
		"token",
		"username",
		"created",
		"expire"
	);

	public int $id;

	public string $token;

	public string $username;

	public int $created;

	public int $expire;

	public function validate(): bool {
		return ($this -> expire >= time());
	}

	/**
	 * Push the expiry out by a full lifetime, from now.
	 */
	public function renew(): static {
		$this -> created = time();
		$this -> expire = $this -> created + CONFIG::$TOKEN_LIFETIME;
		$this -> save();

		return $this;
	}

	/**
	 * Resolve a token string, rejecting one that is unknown or expired.
	 *
	 * @throws	InvalidToken
	 * @throws	TokenExpired
	 */
	public static function getToken(string $token): static {
		$instance = static::where("token", $token) -> first();

		if (empty($instance))
			throw new InvalidToken();

		if (!$instance -> validate())
			throw new TokenExpired();

		return $instance;
	}

	/**
	 * A usable token for a user, renewed or created as needed.
	 */
	public static function forUser(string $username): static {
		$instance = static::where("username", $username) -> first();

		if (empty($instance))
			return static::createToken($username);

		if (!$instance -> validate())
			$instance -> renew();

		return $instance;
	}

	public static function createToken(string $username): static {
		$created = time();

		return static::create(array(
			"token" => bin2hex(random_bytes(64)),
			"username" => $username,
			"created" => $created,
			"expire" => $created + CONFIG::$TOKEN_LIFETIME
		)) -> save();
	}
}
