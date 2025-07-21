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
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CoreConfig {
	public static string	$APP_NAME = "My Web App";

	public static int		$SESSION_LIFETIME = 86400;
	public static int		$TOKEN_LIFETIME = 86400;
	public static string	$BLINK_VERSION = "1.0.0";
	public static string	$BLINK_URL = "https://github.com/Belikhun/blink";
	public static string	$ENV = "default";

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

	public static array		$INCLUDES = array(
		CORE_ROOT . "/includes",
		CORE_ROOT . "/classes",
		CORE_ROOT . "/middleware",
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
}
