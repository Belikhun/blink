<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Lightrail;
use Blink\Lightrail\Model\HandlerLog;

/**
 * Run a failed handler again.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LightrailRetry extends Command {
	public static string $usage = "lightrail:retry {handler? : The failed handler row to run again} {--all : Retry every failed handler} {--limit=50 : How many to retry with --all}";

	public static string $description = "Run failed event handlers again";

	public function run() {
		$id = $this -> argument("handler");

		if (!empty($id))
			return $this -> retry((int) $id);

		if (!$this -> option("all")) {
			$this -> warning("Name a handler row to retry, or pass --all.");
			return Console::EXIT_USAGE;
		}

		$failed = HandlerLog::query()
			-> where(HandlerLog::col("status"), HandlerLog::STATUS_FAILED)
			-> sort(HandlerLog::col("id"), "ASC")
			-> all(0, max((int) $this -> option("limit"), 1));

		if (empty($failed)) {
			$this -> info("No failed handlers.");
			return Console::EXIT_OK;
		}

		$code = Console::EXIT_OK;

		foreach ($failed as $handler) {
			if ($this -> retry($handler -> id) !== Console::EXIT_OK)
				$code = Console::EXIT_FAILURE;
		}

		return $code;
	}

	protected function retry(int $handlerID): int {
		$results = Lightrail::coordinator() -> retryHandlerNow($handlerID);

		if (empty($results)) {
			$this -> warning("#{$handlerID} could not be retried. It may not have failed, or its event class is gone.");
			return Console::EXIT_OK;
		}

		$code = Console::EXIT_OK;

		foreach ($results as $result) {
			if (!empty($result["errored"])) {
				$this -> error("failed   {$result['handler']}  " . $result["error"]);
				$code = Console::EXIT_FAILURE;
				continue;
			}

			$this -> success("{$result['status']}  {$result['handler']}");
		}

		return $code;
	}
}
