<?php

use Blink\Environment;
use Blink\Metric\Timing;
use Blink\Router;
use Blink\Session;

$BLINK_START = microtime(true);

ini_set("short_open_tag", true);

/**
 * setup.php
 * 
 * Web core setup file for initializing components,
 * session and handle current request's routing.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

setlocale(LC_TIME, "vi_VN.UTF-8");
date_default_timezone_set("Asia/Ho_Chi_Minh");

/**
 * Server's base path.
 * 
 * @var	string
 */
define("BASE_PATH", str_replace("\\", "/", getcwd()));

/**
 * Server's application root folder. Alias to {@link BASE_PATH}
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
 * @var		\Blink\DB	$DB
 * @global	\Blink\DB	$DB
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

/**
 * Current requested path
 * @var string
 */
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



//* ===========================================================
//*  Initialize Config Store and Autoload
//* -----------------------------------------------------------
//*  Try to load config defined in application, fallback to
//*  core default config if none exist.
//*  
//*  This also setup class autoload.
//* ===========================================================

require_once CORE_ROOT . "/const.php";
require_once CORE_ROOT . "/config.php";

if (!file_exists(BASE_PATH . "/.htaccess")) {
	copy(CORE_ROOT . "/files/index.htaccess", BASE_PATH . "/.htaccess");
	header("Location: /", true);
	die();
}

if (!file_exists(BASE_PATH . "/config.define.php")) {
	require_once CORE_ROOT . "/defaults/Config.php";
} else {
	require_once BASE_PATH . "/config.define.php";
}

if (!file_exists(BASE_PATH . "/config.php")) {
	if (file_exists(BASE_PATH . "/config.default.php")) {
		copy(BASE_PATH . "/config.default.php", BASE_PATH . "/config.php");
		require_once BASE_PATH . "/config.php";
	}
} else
	require_once BASE_PATH . "/config.php";

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
	copy(CORE_ROOT . "/files/index.gitignore", BASE_PATH . "/.gitignore");

// We can safely include our main library now.
require_once CORE_ROOT . "/libs.php";
require_once CORE_ROOT . "/handlers.php";

// We have registered our handlers, dispose of all garbage output from now.
// This is to prevent headers from being sent early.
ob_start();

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

if (!class_exists(User::class))
	require_once CORE_ROOT . "/defaults/User.php";

$runtimeInitTiming = new Timing("runtime init");
$runtimeInitTiming -> start = $_SERVER["REQUEST_TIME_FLOAT"];
$runtimeInitTiming -> time = $BLINK_START;

$setupTiming = new Timing("setup");
$setupTiming -> start = $BLINK_START;
$setupTiming -> time();

$configTiming = new Timing("config");

// Initialize config store.
if (file_exists(BASE_PATH . "/config.store.php")) {
	\Config\Store::$CONFIG_PATH = DATA_ROOT . "/config.json";
	require_once BASE_PATH . "/config.store.php";
	\Config\Store::init();
}

$configTiming -> time();

// Pre-setup DB
require_once CORE_ROOT . "/db/DB.Abstract.php";



//* ===========================================================
//*  Additional Page Setup
//* -----------------------------------------------------------
//*  Just initialize current session and run additional setup
//*  process defined in application.
//* ===========================================================

new Timing("env", function () {
	// Initialize environment variables
	Environment::load(\CONFIG::$ENV);
});

new Timing("session", function() {
	// Initialize session
	if (class_exists(Session::class))
		Session::start();
});

// Add default endpoint for error page.
Router::GET("/error/{id}", [ \Blink\ErrorPage\Instance::class, "handle" ], -1);
Router::GET("/error", [ \Blink\ErrorPage\Instance::class, "index" ], -1);

if (file_exists(BASE_PATH . "/setup.php"))
	require_once BASE_PATH . "/setup.php";



//* ===========================================================
//*  Handle Routing
//* -----------------------------------------------------------
//*  Include all route definition files and start routing based
//*  on current request path and method.
//* ===========================================================

// Include routes definition before start routing.
$routesPath = CONFIG::$ROUTES_ROOT;
$routesTiming = new Timing("route init");

foreach (glob("$routesPath/*.php") as $filename) {
	// Isolate scope
	(function ($currentFileLocation) {
		require_once $currentFileLocation;
	})($filename);
}

$routesTiming -> time();

// Handle current request path
Router::route($PATH, $_SERVER["REQUEST_METHOD"]);
