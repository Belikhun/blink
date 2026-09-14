<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Http\Router;
use function Blink\stringify;

/**
 * Show every registered route, in the order the router checks them.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class RouteList extends Command {
	public static string $usage = "route:list {--path= : Only show routes whose URI contains this} {--method= : Only show routes answering this verb}";

	public static string $description = "List the registered routes";

	public function run() {
		// Routes live in the files the router loads per request, which a
		// console command skips. Load them so there is something to list.
		Router::loadRoutesFrom(\CONFIG::$ROUTES_ROOT);

		$routes = Router::getRoutes();

		usort($routes, fn ($a, $b) => $b -> priority <=> $a -> priority);

		$path = $this -> option("path");
		$method = strtoupper((string) $this -> option("method"));
		$rows = array();

		foreach ($routes as $route) {
			if (!empty($path) && !str_contains($route -> uri, $path))
				continue;

			if (!empty($method) && !in_array($method, $route -> verbs, true))
				continue;

			$verbs = (count($route -> verbs) === count(Router::$verbs))
				? "ANY"
				: implode("|", $route -> verbs);

			$middleware = array_merge($route -> getMiddleware(), array_map(
				fn ($reference) => "!{$reference}",
				$route -> getExcludedMiddleware()
			));

			$rows[] = array(
				$route -> priority,
				$verbs,
				$route -> uri,
				$route -> file ?? "",
				stringify($route -> action),
				implode(",", $middleware)
			);
		}

		if (empty($rows)) {
			$this -> warning("No routes matched.");
			return Console::EXIT_OK;
		}

		$this -> table(
			array( "pri", "method", "uri", "file", "action", "middleware" ),
			$rows
		);

		$this -> line();
		$this -> comment(count($rows) . " route(s).");

		return Console::EXIT_OK;
	}
}
