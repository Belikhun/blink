<?php

namespace Blink\Ticker\Exception;

use Blink\Exception\BaseException;

/**
 * A queued row no longer describes a task that exists.
 *
 * Usually a class that was renamed or removed while rows referring to it were
 * still waiting. The task is failed immediately rather than retried, since no
 * number of attempts will bring the class back.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TaskUnserializable extends BaseException {
	public int $queuedId;

	public function __construct(int $queuedId, string $found) {
		$this -> queuedId = $queuedId;

		parent::__construct(
			TICKER_TASK_UNSERIALIZABLE,
			"Queued task #{$queuedId} could not be restored.",
			500,
			array( "id" => $queuedId, "found" => $found ),
			"The payload came back as <code>{$found}</code>. The class it was queued with has probably been renamed or removed."
		);
	}
}
