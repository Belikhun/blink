<?php

/**
 * Property attribute that declare an config property
 * is a path. This path will be initialized if it's not
 * already.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ConfigPathProperty {}

/**
 * config.php
 * 
 * Predefine configs that web core needed in order to function.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class CoreConfig {
	/**
	 * Page title
	 * @var string
	 */
	public static String	$APP_NAME = "My Web App";

	public static int		$SESSION_LIFETIME = 86400;
	public static int		$TOKEN_LIFETIME = 86400;
	public static String	$BLINK_VERSION = "1.0.0";
	public static String	$BLINK_URL = "https://github.com/Belikhun/blink";
	public static String	$ENV = "default";

	public static String	$DB_DRIVER = "MySQLi";
	public static String	$DB_HOST = "127.0.0.1";
	public static String	$DB_USER = "";
	public static String	$DB_PASS = "";
	public static String	$DB_NAME = "";
	public static String	$DB_PATH = BASE_PATH . "/db";

	#[ConfigPathProperty]
	public static String	$FILES_ROOT = DATA_ROOT . "/files";

	#[ConfigPathProperty]
	public static String	$IMAGES_ROOT = DATA_ROOT . "/images";

	#[ConfigPathProperty]
	public static String	$CACHE_ROOT = DATA_ROOT . "/caches";
	
	#[ConfigPathProperty]
	public static String	$ERRORS_ROOT = DATA_ROOT . "/errors";

	#[ConfigPathProperty]
	public static String	$ROUTES_ROOT = BASE_PATH . "/routes";
	
	#[ConfigPathProperty]
	public static String	$TEMPLATES_ROOT = BASE_PATH . "/templates";

	public static Array		$INCLUDES = Array(
		CORE_ROOT . "/includes",
		CORE_ROOT . "/classes",
		CORE_ROOT . "/db/Exception",
		CORE_ROOT . "/middleware"
	);

	public static String	$FILE_STORE = FILE_STORE_FS;

	public static bool		$DEBUG = true;

	public static Array		$IMAGE_ALLOW = Array("png", "jpg", "webp", "gif", "tif", "jpeg", "bmp");
	public static int		$IMAGE_SIZE = 6291456;
	public static bool		$W_MODE = true;
}
