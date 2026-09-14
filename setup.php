<?php

use Blink\DB\Database;
use Blink\Environment;
use Blink\Localization;
use Blink\Metric\TimingMetric;
use Blink\Http\Router;
use Blink\Server;
use Blink\Session;
use Blink\StopClock;
use function Blink\getHeader;

$BLINK_START = microtime(true);

ini_set("short_open_tag", true);

/**
 * setup.php
 *
 * Blink's bootstrap. Brings the framework up for a web request or a console
 * command, then hands the request to the router.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

// A placeholder until CONFIG loads and the real zone is applied below. Date
// functions warn without one, and config cannot be read this early.
date_default_timezone_set("UTC");

/**
 * Whether blink is running from the console rather than answering a request.
 *
 * The console entry point defines it before getting here; anything else is
 * detected from the SAPI.
 *
 * @var	bool
 */
if (!defined("BLINK_IS_CLI"))
	define("BLINK_IS_CLI", (PHP_SAPI === "cli" || PHP_SAPI === "phpdbg"));

/**
 * Server's base path.
 *
 * The console entry point resolves and defines it before getting here, since
 * the working directory of a command says nothing about which site it targets.
 *
 * @var	string
 */
if (!defined("BASE_PATH"))
	define("BASE_PATH", str_replace("\\", "/", getcwd()));

/**
 * Server's application root folder. Alias to {@see BASE_PATH}
 *
 * @var	string
 */
define("APP_ROOT", BASE_PATH);

/**
 * Path stores all the vendor packages.
 *
 * @var	string
 */
define("VENDOR_ROOT", BASE_PATH . "/vendor");

/**
 * Server's data path.
 *
 * @var	string
 */
define("DATA_ROOT", BASE_PATH . "/data");

/**
 * Core base path.
 *
 * @var	string
 */
define("CORE_ROOT", str_replace("\\", "/", pathinfo(__FILE__, PATHINFO_DIRNAME)));


// Set up intellisense for global variables.

/**
 * Global Database Instance. Initialized based on type of
 * SQL driver specified in config.
 *
 * @var		\Blink\DB\Database		$DB
 * @global	\Blink\DB\Database		$DB
 */

//* ===========================================================
//*  Parse Current Path Information
//* -----------------------------------------------------------
//*  Parse current path information to make it available to
//*  core as soon as possible.
//* ===========================================================

/**
 * Current requested path
 * @var string
 */
global $PATH;

if (!BLINK_IS_CLI) {
	$PATH = $_GET["path"];
	unset($_GET["path"]);

	// Little hack for nginx server
	if (isset($_GET["params"])) {
		$PATH .= "?". $_GET["params"];
		unset($_GET["params"]);
	}

	$URL = parse_url($PATH);
	$PATH = $URL["path"];

	if (!empty($URL["query"])) {
		$GET = [];
		parse_str($URL["query"], $GET);
		$_GET = array_merge($_GET, $GET);
	}

	// Special rules for index page
	if ($PATH === "/index" || $PATH === "/index.php")
		$PATH = "/";
}



//* ===========================================================
//*  Initialize Config Store and Autoload
//* -----------------------------------------------------------
//*  Load the application's config, then bring up the library
//*  functions, error handlers and class autoloading.
//* ===========================================================

require_once CORE_ROOT . "/consts.php";
require_once CORE_ROOT . "/config.php";

if (!BLINK_IS_CLI && !file_exists(BASE_PATH . "/.htaccess")) {
	copy(CORE_ROOT . "/files/root.htaccess", BASE_PATH . "/.htaccess");
	header("Location: /", true);
	die();
}

if (file_exists(BASE_PATH . "/config.define.php"))
	require_once BASE_PATH . "/config.define.php";

if (!file_exists(BASE_PATH . "/config.php")) {
	if (file_exists(BASE_PATH . "/config.default.php")) {
		copy(BASE_PATH . "/config.default.php", BASE_PATH . "/config.php");
		require_once BASE_PATH . "/config.php";
	}
} else {
	require_once BASE_PATH . "/config.php";
}

if (!class_exists(\CONFIG::class)) {
	echo "Define your \"CONFIG\" class inside \"config.define.php\"!!!";
	http_response_code(500);
	die();
}

if (!in_array(CoreConfig::class, class_parents(\CONFIG::class, false))) {
	echo "Your \"CONFIG\" class MUST extend \"CoreConfig\"!!!";
	http_response_code(500);
	die();
}

date_default_timezone_set(CONFIG::$TIMEZONE);

// Initialise paths in config
$configRef = new ReflectionClass(CONFIG::class);
$configProps = $configRef -> getProperties(ReflectionProperty::IS_STATIC);
foreach ($configProps as $prop) {
	$attrs = $prop -> getAttributes();

	if (empty($attrs))
		continue;

	foreach ($attrs as $attr) {
		if ($attr -> getName() === ConfigPathProperty::class) {
			$value = $prop -> getValue();

			if (!file_exists($value))
				mkdir($value, recursive: true);
		}
	}
}

if (!file_exists(DATA_ROOT . "/.htaccess"))
	copy(CORE_ROOT . "/files/data.htaccess", DATA_ROOT . "/.htaccess");

if (!file_exists(BASE_PATH . "/.gitignore"))
	copy(CORE_ROOT . "/files/root.gitignore", BASE_PATH . "/.gitignore");

if (!file_exists(BASE_PATH . "/.editorconfig"))
	copy(CORE_ROOT . "/files/root.editorconfig", BASE_PATH . "/.editorconfig");

// We can safely include our main library now.
require_once CORE_ROOT . "/libs.php";
require_once CORE_ROOT . "/handlers.php";
require_once CORE_ROOT . "/autoload.php";

if (!BLINK_IS_CLI) {
	// We have registered our handlers, dispose of all garbage output from now.
	// This is to prevent headers from being sent early.
	ob_start();
}

/**
 * Clock for tracking runtime since request started.
 *
 * @var	StopClock	$RUNTIME
 */
global $RUNTIME;

if (!isset($RUNTIME)) {
	$RUNTIME = new StopClock();
	$RUNTIME -> start = $BLINK_START;
}

$runtimeInitTiming = new TimingMetric("php init");
$runtimeInitTiming -> start = $_SERVER["REQUEST_TIME_FLOAT"];
$runtimeInitTiming -> time = $BLINK_START;

$setupTiming = new TimingMetric("setup");
$setupTiming -> start = $BLINK_START;
$setupTiming -> time();

// Initialize localization and language store.
new TimingMetric("localization", function () {
	Localization::setup();
});

new TimingMetric("env", function () {
	// Initialize environment variables
	Environment::load(\CONFIG::$ENV);
});


//* ===========================================================
//*  Additional Page Setup
//* -----------------------------------------------------------
//*  Parse the server environment, connect the database and let
//*  every module wire itself up.
//* ===========================================================

if (BLINK_IS_CLI)
	Server::setupCli();
else
	Server::setup();

Database::initialize();

new TimingMetric("modules", function () {
	Blink::loadModules();
});

if (BLINK_IS_CLI)
	return;

new TimingMetric("session", function() {
	// Initialize session
	if (class_exists(Session::class)) {
		$auth = getHeader("Authorization");

		if (!empty($auth)) {
			$auth = explode(" ", $auth);

			if (empty($auth[1])) {
				$token = $auth[0];
				$type = "Basic";
			} else {
				list($type, $token) = $auth;
			}

			switch ($type) {
				case "Basic":
					Session::start();
					Session::token($token);
					break;

				case "Session":
					Session::start($token);
					break;
			}
		} else {
			Session::start();
		}
	}
});


//* ===========================================================
//*  Handle Routing
//* -----------------------------------------------------------
//*  Include all route definition files and start routing based
//*  on current request path and method.
//* ===========================================================

new TimingMetric("route init", function () {
	Router::loadRoutesFrom(CONFIG::$ROUTES_ROOT);
});

// Handle current request path
Router::route($PATH, Server::$METHOD);
