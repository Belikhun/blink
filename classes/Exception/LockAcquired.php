<?php

namespace Blink\Exception;

/**
 * A lock is already held by someone else.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LockAcquired extends BaseException {
	public string $lock;

	public function __construct(string $lock, string $owner = null, int $expires = null) {
		$this -> lock = $lock;

		$details = !empty($owner)
			? "Held by <code>{$owner}</code>."
			: "The current holder did not record where it took the lock.";

		if (!empty($expires))
			$details .= " Expires in " . max($expires - time(), 0) . "s.";

		parent::__construct(
			LOCK_ALREADY_ACQUIRED,
			"Lock <code>{$lock}</code> is already held.",
			500,
			array( "lock" => $lock, "owner" => $owner, "expires" => $expires ),
			$details
		);
	}
}
