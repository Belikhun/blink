<?php
/**
 * User.php
 * 
 * Default User class. Only included if page haven't
 * defined its own User class.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

abstract class User {
	public ?int $id;

	public ?string $username;

	abstract public function __construct(int $id, string $username);

	abstract public static function getByID(int $id): static;
	abstract public static function getByUsername(string $username): static;
}
