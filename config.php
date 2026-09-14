<?php

/**
 * Property attribute that declare an config property
 * is a path. This path will be initialized if it's not
 * already.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ConfigPathProperty {}

/**
 * Predefine configs that blink core needed in order to function.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CoreConfig {
	public static string	$APP_NAME = "My Web App";

	/**
	 * Application version. Bump it on every deploy: it feeds the cache version
	 * that invalidates the autoload, Ether and Lightrail caches together.
	 */
	public static string	$APP_VERSION = "1.0.0";

	public static int		$SESSION_LIFETIME = 86400;
	public static int		$TOKEN_LIFETIME = 86400;
	public static string	$BLINK_VERSION = "2.0.0-dev";
	public static string	$BLINK_URL = "https://github.com/Belikhun/blink";
	public static string	$ENV = "default";

	/**
	 * Modules the application boots, in order. Each must extend
	 * {@see \Blink\Module}. Blink's own core module is always loaded first.
	 *
	 * @var class-string<\Blink\Module>[]
	 */
	public static array		$MODULES = array();

	public static string	$TIMEZONE = "UTC";
	public static string	$LANG = "en";

	public static string	$DB_DRIVER = "MySQLi";
	public static string	$DB_HOST = "127.0.0.1";
	public static string	$DB_USER = "";
	public static string	$DB_PASS = "";
	public static string	$DB_NAME = "";
	public static string	$DB_PATH = BASE_PATH . "/db";

	#[ConfigPathProperty]
	public static string	$FILES_ROOT = DATA_ROOT . "/files";

	#[ConfigPathProperty]
	public static string	$CACHE_ROOT = DATA_ROOT . "/caches";

	#[ConfigPathProperty]
	public static string	$ERRORS_ROOT = DATA_ROOT . "/errors";

	#[ConfigPathProperty]
	public static string	$ROUTES_ROOT = BASE_PATH . "/routes";

	#[ConfigPathProperty]
	public static string	$TEMPLATES_ROOT = BASE_PATH . "/templates";

	/**
	 * Namespace prefix to directory map the autoloader resolves against, longest
	 * prefix first. The `Blink\` prefix is added by the autoloader and cannot be
	 * overridden here.
	 *
	 * @var array<string, string>
	 */
	public static array		$AUTOLOAD = array(
		"App\\" => BASE_PATH . "/classes"
	);

	/**
	 * Directories the autoloader falls back to scanning when path resolution
	 * through `$AUTOLOAD` finds nothing. It catches the two shapes a path
	 * cannot describe: a file holding more than one class, and a class in the
	 * global namespace.
	 *
	 * @var string[]
	 */
	public static array		$INCLUDES = array(
		BASE_PATH . "/includes",
		BASE_PATH . "/classes"
	);

	public static array		$LANGS = array(
		CORE_ROOT . "/lang",
		BASE_PATH . "/lang"
	);

	public static string	$FILE_STORE = FILE_STORE_FS;

	public static bool		$DEBUG = true;

	public static array		$IMAGE_ALLOW = array("png", "jpg", "webp", "gif", "tif", "jpeg", "bmp");
	public static int		$IMAGE_SIZE = 6291456;
	public static bool		$REVERSE_PROXY = false;
	public static bool		$W_MODE = true;

	/**
	 * Mode files are created with. Applied after the atomic write in
	 * {@see \Blink\filePut()}, which stages through a temporary file that would
	 * otherwise inherit the restrictive permissions `tempnam()` gives it.
	 */
	public static int		$FILE_PERMISSIONS = 0644;


	//* ===========================================================
	//*  Terra
	//* ===========================================================

	/**
	 * Apply pending schema layers on the first web request that notices them.
	 * Turn it off on multi-host deployments and run `terra:apply` at deploy
	 * time instead, since the guard against concurrent runs is a file lock and
	 * only reaches the local host.
	 */
	public static bool		$TERRA_AUTO_APPLY = true;

	/**
	 * Seconds a request waits for another process to finish applying layers
	 * before giving up and reporting the database as not upgraded.
	 */
	public static int		$TERRA_WAIT = 10;

	/**
	 * Extra directories to discover schema layers in, on top of blink's own and
	 * the application's `db/terra`.
	 *
	 * @var string[]
	 */
	public static array		$TERRA_PATHS = array();


	//* ===========================================================
	//*  Ticker
	//* ===========================================================

	/**
	 * Where queued tasks are kept. `database` queues them for a runner to drain,
	 * `direct` runs each task inline at queue time for sites with no runner.
	 */
	public static string	$TICKER_STORE = "database";

	public static string	$TICKER_QUEUE = "default";

	/**
	 * Seconds before a claimed task is considered abandoned and offered to
	 * another runner. Only used for tasks that declare no timeout of their own.
	 */
	public static int		$TICKER_RETRY_AFTER = 90;


	//* ===========================================================
	//*  Lock
	//* ===========================================================

	/**
	 * Where cross-process locks live. `database` reaches every host sharing the
	 * database; `file` only covers the local filesystem.
	 */
	public static string	$LOCK_STORE = "database";


	//* ===========================================================
	//*  Lightrail
	//* ===========================================================

	public static string	$LIGHTRAIL_QUEUE = "lightrail";

	/**
	 * Record a dispatch and handler trail for every event. Turning it off also
	 * rejects the background and batched execution modes, which both resume
	 * from a persisted dispatch row.
	 */
	public static bool		$LIGHTRAIL_TRAIL = true;


	//* ===========================================================
	//*  Console
	//* ===========================================================

	/**
	 * Memory limit raised for console commands, applied only when the current
	 * limit is lower. A runner draining thousands of tasks needs more headroom
	 * than a web request.
	 */
	public static string	$CLI_MEMORY_LIMIT = "512M";
}
