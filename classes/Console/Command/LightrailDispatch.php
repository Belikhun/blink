<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Lightrail\Model\DispatchLog;
use Blink\Lightrail\Model\HandlerLog;
use Blink\Model;
use function Blink\relativeTime;

/**
 * Show recent dispatches, or one in detail.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LightrailDispatch extends Command {
	public static string $usage = "lightrail:dispatch {id? : Show this dispatch and its handlers} {--limit=20 : How many to list} {--status= : Only dispatches in this state} {--run : Run the pending handlers of the named dispatch now}";

	public static string $description = "Inspect the dispatch trail";

	public function run() {
		$id = $this -> argument("id");

		return empty($id)
			? $this -> listDispatches()
			: $this -> showDispatch((int) $id);
	}

	protected function listDispatches(): int {
		$query = DispatchLog::query() -> sort(DispatchLog::col("id"), "DESC");
		$status = $this -> option("status");

		if (!empty($status))
			$query -> where(DispatchLog::col("status"), $status);

		$dispatches = $query -> all(0, max((int) $this -> option("limit"), 1));

		if (empty($dispatches)) {
			$this -> info("Nothing dispatched yet.");
			return Console::EXIT_OK;
		}

		$rows = array();

		foreach ($dispatches as $dispatch) {
			$rows[] = array(
				$dispatch -> id,
				$dispatch -> eventClass,
				$dispatch -> dispatchMode,
				$dispatch -> status,
				"{$dispatch -> completedHandlers}/{$dispatch -> totalHandlers}",
				$dispatch -> errorHandlers,
				sprintf("%.1fms", $dispatch -> duration),
				relativeTime($dispatch -> created)
			);
		}

		$this -> table(
			array( "id", "event", "mode", "status", "done", "errors", "took", "when" ),
			$rows
		);

		return Console::EXIT_OK;
	}

	protected function showDispatch(int $id): int {
		$dispatch = DispatchLog::getByID($id, Model::MUST_EXIST);

		$this -> heading("dispatch #{$dispatch -> id}");
		$this -> table(array(), array(
			array( "  event", $dispatch -> eventClass ),
			array( "  mode", $dispatch -> dispatchMode ),
			array( "  status", $dispatch -> status ),
			array( "  handlers", "{$dispatch -> completedHandlers} of {$dispatch -> totalHandlers} done, {$dispatch -> errorHandlers} failed" ),
			array( "  duration", sprintf("%.2fms", $dispatch -> duration) ),
			array( "  when", relativeTime($dispatch -> created) ),
			array( "  correlation", $dispatch -> correlationID )
		));

		$context = $dispatch -> eventPayload["context"] ?? array();

		if (!empty($context)) {
			$this -> heading("context");
			$rows = array();

			foreach ($context as $key => $value) {
				$rows[] = array(
					"  " . $key,
					is_scalar($value) ? (string) $value : json_encode($value)
				);
			}

			$this -> table(array(), $rows);
		}

		$handlers = HandlerLog::query()
			-> where(HandlerLog::col("dispatchID"), $dispatch -> id)
			-> sort(HandlerLog::col("id"), "ASC")
			-> all();

		if (!empty($handlers)) {
			$this -> heading("handlers");
			$rows = array();

			foreach ($handlers as $handler) {
				$rows[] = array(
					$handler -> id,
					$handler -> handler,
					$handler -> executionMode,
					$handler -> status,
					$handler -> attemptNumber,
					sprintf("%.1fms", $handler -> duration),
					$handler -> reportID ?? "",
					$handler -> errorMessage ?? ""
				);
			}

			$this -> table(
				array( "id", "handler", "mode", "status", "try", "took", "report", "error" ),
				$rows
			);
		}

		if ($this -> option("run")) {
			$this -> line();
			$results = \Blink\Lightrail::coordinator() -> dispatchPendingNow($dispatch -> id);

			if (empty($results)) {
				$this -> info("Nothing was pending.");
				return Console::EXIT_OK;
			}

			foreach ($results as $result) {
				$line = "{$result['status']}  {$result['handler']}";

				if (!empty($result["error"]))
					$this -> error($line . "  " . $result["error"]);
				else
					$this -> success($line);
			}
		}

		return Console::EXIT_OK;
	}
}
