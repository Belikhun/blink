<?php

namespace Blink\Module;

use Blink\Console\Command;
use Blink\ErrorPage;
use Blink\Observer\ModelAuditObserver;
use Blink\Ether;
use Blink\Http\Router;
use Blink\Http\Router\Middleware\ApplyCorsHeaders;
use Blink\Http\Router\Middleware\AuthenticateUser;
use Blink\Http\Router\Middleware\SetupSessionEnvironment;
use Blink\Module;
use Blink\Ticker\Orbit;

/**
 * Blink's own module. Always the first one booted.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CoreModule extends Module {
	public function define(): void {
		\Blink::registerCoreSingletons();
	}

	public function attach(): void {
		// Authentication runs ahead of the environment setup, so a middleware
		// reading the session language gets the authenticated user's.
		Ether::use(AuthenticateUser::class, "auth", 200);
		Ether::use(SetupSessionEnvironment::class, "setupEnv", 100);
		Ether::use(ApplyCorsHeaders::class, "cors", 100);

		// Named so a route can opt the pair out together with
		// `withoutMiddleware("router")`, which is what an endpoint serving
		// unauthenticated traffic wants.
		Ether::group("router", "auth", "setupEnv");

		Router::$processingFile = "blink:core";

		// Negative priority keeps these behind anything the application
		// registers on the same paths.
		Router::GET("/error/{id}", [ ErrorPage::class, "handle" ], -1);
		Router::GET("/error", [ ErrorPage::class, "index" ], -1);

		Router::$processingFile = null;
	}

	public function orbits(Orbit $orbit): void {
		// Sweeps up batch buffers whose flush never arrived, which a runner
		// that died mid-task or a task that exhausted its retries can leave.
		$orbit -> command("lightrail:flush --stale")
			-> everyHour()
			-> alone()
			-> name("lightrail.flush-stale");

		// Audit entries past the retention window, in the small hours.
		$orbit -> command("trace:purge --force")
			-> everyDay("03:30")
			-> alone()
			-> name("trace.purge");
	}

	public function observers(): array {
		return array(
			ModelAuditObserver::class
		);
	}

	public function commands(): array {
		return array(
			Command\ListCommand::class,
			Command\HelpCommand::class,
			Command\AboutCommand::class,

			Command\TerraApply::class,
			Command\TerraStatus::class,
			Command\TerraRevert::class,
			Command\TerraReset::class,
			Command\TerraRebuild::class,
			Command\TerraMake::class,

			Command\TickerRun::class,
			Command\TickerTick::class,
			Command\TickerOrbits::class,
			Command\TickerStatus::class,
			Command\TickerFailed::class,
			Command\TickerRetry::class,
			Command\TickerForget::class,
			Command\TickerClear::class,
			Command\TickerRestart::class,

			Command\LightrailListeners::class,
			Command\LightrailDispatch::class,
			Command\LightrailRetry::class,
			Command\LightrailFlush::class,
			Command\LightrailPurge::class,

			Command\CacheClear::class,
			Command\RouteList::class,

			Command\ErrorList::class,
			Command\ErrorPurge::class,
			Command\TraceList::class,
			Command\TracePurge::class,

			Command\LockList::class,
			Command\LockRelease::class
		);
	}
}
