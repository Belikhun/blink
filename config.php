<?php

use Blink\Attribute\FromEnv;
use Blink\Attribute\PathProperty;
use Blink\Attribute\Secret;

/**
 * The settings blink needs in order to run.
 *
 * An application extends this in `config.define.php`, adds whatever settings
 * of its own it needs, and sets values in `config.php`. A module ships its
 * settings as a config class of its own; see {@see \Blink\Config}.
 *
 * A setting marked {@see FromEnv} is filled from the environment when that
 * variable is set, converted to the property's declared type. One marked
 * {@see Secret} is masked everywhere blink displays configuration, and one
 * marked {@see PathProperty} names a directory blink creates on boot.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CoreConfig {
	/**
	 * What blink's own settings are addressed by: `blink.db_host`.
	 */
	const PREFIX = "blink";


	//* ===========================================================
	//*  Application
	//* ===========================================================

	#[FromEnv("APP_NAME")]
	public static string	$APP_NAME = "My Web App";

	/**
	 * Application version. Bump it on every deploy: it feeds the cache version
	 * that invalidates the autoload, Ether and Lightrail caches together.
	 */
	#[FromEnv("APP_VERSION")]
	public static string	$APP_VERSION = "1.0.0";

	/**
	 * Which env file is read, as a name: `default` reads `.env`, anything else
	 * reads `{name}.env`.
	 *
	 * Deliberately not bound to a variable in the env file, since the file to
	 * read has to be chosen before any of it can be read. Set `APP_ENV` in the
	 * process environment to choose from outside.
	 */
	public static string	$ENV = "default";

	/**
	 * Error pages carry the stack trace, the source around it and the state of
	 * the request. Turn it off in production: a report is reachable by anyone
	 * holding its id.
	 */
	#[FromEnv("APP_DEBUG")]
	public static bool		$DEBUG = true;

	/**
	 * Modules the application boots, in order. Each must extend
	 * {@see \Blink\Module}. Blink's own core module is always loaded first.
	 *
	 * @var class-string<\Blink\Module>[]
	 */
	public static array		$MODULES = array();

	#[FromEnv("APP_TIMEZONE")]
	public static string	$TIMEZONE = "UTC";

	/**
	 * Not bound to `LANG`, which nearly every Linux shell already exports as
	 * something like `en_US.UTF-8`.
	 */
	#[FromEnv("APP_LANG")]
	public static string	$LANG = "en";

	public static string	$BLINK_VERSION = "2.0.0-dev";
	public static string	$BLINK_URL = "https://github.com/Belikhun/blink";

	/**
	 * The sticker on the error page.
	 */
	public static bool		$W_MODE = true;


	//* ===========================================================
	//*  Database
	//* ===========================================================

	/**
	 * Which driver connects to the database: `MySQLi` or `SQLite3`.
	 */
	#[FromEnv("DB_DRIVER")]
	public static string	$DB_DRIVER = "MySQLi";

	#[FromEnv("DB_HOST")]
	public static string	$DB_HOST = "127.0.0.1";

	/**
	 * Port the database listens on. Zero leaves it to the driver, which is
	 * what a default installation wants.
	 */
	#[FromEnv("DB_PORT")]
	public static int		$DB_PORT = 0;

	#[FromEnv("DB_USER")]
	public static string	$DB_USER = "";

	#[FromEnv("DB_PASS")]
	#[Secret]
	public static string	$DB_PASS = "";

	#[FromEnv("DB_NAME")]
	public static string	$DB_NAME = "";

	/**
	 * Where SQLite keeps its file.
	 */
	#[FromEnv("DB_PATH")]
	public static string	$DB_PATH = BASE_PATH . "/db";


	//* ===========================================================
	//*  Paths
	//* ===========================================================

	#[PathProperty]
	public static string	$FILES_ROOT = DATA_ROOT . "/files";

	#[PathProperty]
	public static string	$CACHE_ROOT = DATA_ROOT . "/caches";

	#[PathProperty]
	public static string	$ERRORS_ROOT = DATA_ROOT . "/errors";

	#[PathProperty]
	public static string	$ROUTES_ROOT = BASE_PATH . "/routes";

	#[PathProperty]
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

	/**
	 * Directories language files are read from, later ones overriding earlier.
	 *
	 * @var string[]
	 */
	public static array		$LANGS = array(
		CORE_ROOT . "/lang",
		BASE_PATH . "/lang"
	);


	//* ===========================================================
	//*  Files
	//* ===========================================================

	/**
	 * Which store holds file content. `local` keeps it on disk under
	 * {@see CoreConfig::$FILES_ROOT}; a module can register another with
	 * {@see \Blink\File::useStore()}.
	 */
	#[FromEnv("FILE_STORE")]
	public static string	$FILE_STORE = "local";

	/**
	 * Algorithm files are addressed by. Content is stored under its hash, so
	 * two uploads of the same bytes share one copy, and a hash anyone can
	 * forge would let a crafted upload take the place of an existing file.
	 *
	 * Changing this on a site that already holds files leaves them
	 * unreachable: their rows carry hashes in the old algorithm.
	 */
	public static string	$FILE_HASH = "sha256";

	/**
	 * How many directory levels the local store nests content under, two hex
	 * characters each. Two levels spread a few million files thinly enough
	 * that no single directory listing becomes the bottleneck.
	 */
	public static int		$FILE_SHARD_DEPTH = 2;

	/**
	 * Mode files are created with. Applied after the atomic write in
	 * {@see \Blink\filePut()}, which stages through a temporary file that would
	 * otherwise inherit the restrictive permissions `tempnam()` gives it.
	 */
	public static int		$FILE_PERMISSIONS = 0644;

	/**
	 * Extensions {@see \Blink\Http\UploadedFile::image()} accepts.
	 *
	 * @var string[]
	 */
	public static array		$IMAGE_ALLOW = array( "png", "jpg", "webp", "gif", "tif", "jpeg", "bmp" );

	/**
	 * Largest image accepted, in bytes.
	 */
	public static int		$IMAGE_SIZE = 6291456;


	//* ===========================================================
	//*  Session
	//* ===========================================================

	#[FromEnv("SESSION_LIFETIME")]
	public static int		$SESSION_LIFETIME = 86400;

	#[FromEnv("TOKEN_LIFETIME")]
	public static int		$TOKEN_LIFETIME = 86400;

	/**
	 * Trust the `X-Forwarded-*` headers for the client's address, scheme and
	 * host. Only turn this on behind a proxy you control: anyone can send
	 * those headers directly.
	 */
	#[FromEnv("REVERSE_PROXY")]
	public static bool		$REVERSE_PROXY = false;


	//* ===========================================================
	//*  Terra
	//* ===========================================================

	/**
	 * Apply pending schema layers on the first web request that notices them.
	 * Turn it off on multi-host deployments and run `terra:apply` at deploy
	 * time instead, since the guard against concurrent runs is a file lock and
	 * only reaches the local host.
	 */
	#[FromEnv("TERRA_AUTO_APPLY")]
	public static bool		$TERRA_AUTO_APPLY = true;

	/**
	 * Seconds a request waits for another process to finish applying layers
	 * before giving up and reporting the database as not upgraded.
	 */
	#[FromEnv("TERRA_WAIT")]
	public static int		$TERRA_WAIT = 10;

	/**
	 * Extra directories to discover schema layers in, on top of blink's own and
	 * the application's `db/terra`.
	 *
	 * @var string[]
	 */
	#[FromEnv("TERRA_PATHS")]
	public static array		$TERRA_PATHS = array();


	//* ===========================================================
	//*  Ticker
	//* ===========================================================

	/**
	 * Where queued tasks are kept. `database` queues them for a runner to drain,
	 * `direct` runs each task inline at queue time for sites with no runner.
	 */
	#[FromEnv("TICKER_STORE")]
	public static string	$TICKER_STORE = "database";

	#[FromEnv("TICKER_QUEUE")]
	public static string	$TICKER_QUEUE = "default";

	/**
	 * Seconds before a claimed task is considered abandoned and offered to
	 * another runner. Only used for tasks that declare no timeout of their own.
	 */
	#[FromEnv("TICKER_RETRY_AFTER")]
	public static int		$TICKER_RETRY_AFTER = 90;


	//* ===========================================================
	//*  Lock
	//* ===========================================================

	/**
	 * Where cross-process locks live. `database` reaches every host sharing the
	 * database; `file` only covers the local filesystem.
	 */
	#[FromEnv("LOCK_STORE")]
	public static string	$LOCK_STORE = "database";


	//* ===========================================================
	//*  Lightrail
	//* ===========================================================

	#[FromEnv("LIGHTRAIL_QUEUE")]
	public static string	$LIGHTRAIL_QUEUE = "lightrail";

	/**
	 * Record a dispatch and handler trail for every event. Turning it off also
	 * rejects the background and batched execution modes, which both resume
	 * from a persisted dispatch row.
	 */
	#[FromEnv("LIGHTRAIL_TRAIL")]
	public static bool		$LIGHTRAIL_TRAIL = true;


	//* ===========================================================
	//*  Trace
	//* ===========================================================

	/**
	 * Days an audit entry is kept before `trace:purge` removes it. Zero keeps
	 * every entry forever.
	 */
	#[FromEnv("TRACE_RETENTION_DAYS")]
	public static int		$TRACE_RETENTION_DAYS = 365;

	/**
	 * Entries `trace:purge` deletes per statement.
	 */
	public static int		$TRACE_PURGE_BATCH = 5000;


	//* ===========================================================
	//*  Console
	//* ===========================================================

	/**
	 * Memory limit raised for console commands, applied only when the current
	 * limit is lower. A runner draining thousands of tasks needs more headroom
	 * than a web request.
	 */
	#[FromEnv("CLI_MEMORY_LIMIT")]
	public static string	$CLI_MEMORY_LIMIT = "512M";
}
