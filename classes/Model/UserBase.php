<?php

namespace Blink\Model;

use Blink\Model;

/**
 * Base class for the application's user model.
 *
 * Blink ships no users table of its own: the columns, and the table they live
 * in, belong to the application. What is fixed here is the contract the
 * framework relies on when it resolves a session or a token back to a user.
 *
 * Extend it, declare `$table` and `$fillables` like any other model, then
 * register the class with {@see \Blink::useUserClass()}.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * @template	U
 * @extends		Model<U>
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class UserBase extends Model {
	public int $id;

	/**
	 * The name a user signs in with. Tokens and sessions are keyed on it, so
	 * it has to be unique.
	 */
	public string $username;

	/**
	 * @return	?static
	 */
	abstract public static function getByUsername(string $username);

	/**
	 * The name shown wherever blink has to render this user.
	 */
	public function displayName(): string {
		return $this -> username;
	}
}
