<?php

namespace Blink\Lock;

/**
 * Where locks are kept.
 *
 * Every lock carries an expiry. A holder that dies without releasing leaves a
 * row behind, and the expiry is what lets the next caller take it over instead
 * of waiting on a process that is never coming back.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
abstract class Store {
	/**
	 * Claim a lock, unless someone else holds an unexpired one.
	 *
	 * @param	string	$name	Lock identifier.
	 * @param	string	$token	Random value proving ownership of this claim.
	 * @param	int		$ttl	Seconds before the claim can be taken over.
	 * @param	string	$owner	Where the claim was made, for diagnostics only.
	 * @return	bool	Whether the lock was claimed.
	 */
	abstract public function acquire(string $name, string $token, int $ttl, string $owner): bool;

	/**
	 * Give up a lock, but only if the token still matches.
	 *
	 * The token guard is what stops a holder that overran its TTL from
	 * releasing a lock another process has since claimed.
	 */
	abstract public function release(string $name, string $token): bool;

	/**
	 * Push the expiry out, for a holder still working.
	 */
	abstract public function refresh(string $name, string $token, int $ttl): bool;

	/**
	 * Who holds a lock right now, or null when nobody does.
	 *
	 * @return	?array{name: string, token: string, owner: string, acquired: int, expires: int}
	 */
	abstract public function holder(string $name): ?array;

	/**
	 * Every lock currently held.
	 *
	 * @return	array<string, array{name: string, token: string, owner: string, acquired: int, expires: int}>
	 */
	abstract public function all(): array;

	/**
	 * Drop a lock whoever holds it. Reserved for an operator clearing one by
	 * hand after a crash.
	 */
	abstract public function forget(string $name): bool;
}
