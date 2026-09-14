<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Terra;
use function Blink\convertSize;
use function Blink\getRelativePath;

/**
 * Report what this installation is running.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class AboutCommand extends Command {
	public static string $usage = "about";

	public static string $description = "Show versions, paths and the state of the schema";

	public function run() {
		global $DB;

		$this -> heading("application");
		$this -> table(array(), array(
			array( "  name", \CONFIG::$APP_NAME ),
			array( "  version", \Blink::appVersion() ),
			array( "  environment", \CONFIG::$ENV ),
			array( "  debug", \CONFIG::$DEBUG ? "on" : "off" ),
			array( "  timezone", \CONFIG::$TIMEZONE ),
			array( "  language", \CONFIG::$LANG )
		));

		$this -> heading("blink");
		$this -> table(array(), array(
			array( "  version", \Blink::version() ),
			array( "  php", PHP_VERSION . " (" . PHP_SAPI . ")" ),
			array( "  memory limit", ini_get("memory_limit") ),
			array( "  memory in use", convertSize(memory_get_usage(true)) ),
			array( "  cache version", \Blink::cacheVersion() )
		));

		$this -> heading("paths");
		$this -> table(array(), array(
			array( "  application", BASE_PATH ),
			array( "  core", CORE_ROOT ),
			array( "  data", DATA_ROOT )
		));

		$modules = array();

		foreach (\Blink::modules() as $class => $module)
			$modules[] = array( "  " . $class, count($module -> commands()) . " commands" );

		$this -> heading("modules");
		$this -> table(array(), $modules);

		$this -> heading("database");
		$applied = 0;
		$pending = 0;

		foreach (Terra::status() as $row) {
			if ($row["applied"])
				$applied++;
			else
				$pending++;
		}

		$this -> table(array(), array(
			array( "  driver", \CONFIG::$DB_DRIVER ),
			array( "  family", $DB -> family() ),
			array( "  layers applied", $applied ),
			array( "  layers pending", $pending ),
			array( "  auto apply", \CONFIG::$TERRA_AUTO_APPLY ? "on" : "off" )
		));

		$this -> line();
		return Console::EXIT_OK;
	}
}
