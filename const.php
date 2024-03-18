<?php
/**
 * const.php
 * 
 * Constants definition.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

// Constants
define("FILE_STORE_FS", "fs");
define("FILE_STORE_DB", "db");

// Known mimes
define("MIME_TYPES", Array(
	"txt" => "text/plain",
	"htm" => "text/html",
	"html" => "text/html",
	"php" => "text/html",
	"css" => "text/css",
	"js" => "application/javascript",
	"json" => "application/json",
	"xml" => "application/xml",
	"swf" => "application/x-shockwave-flash",
	"crx" => "application/x-chrome-extension",
	"flv" => "video/x-flv",
	"log" => "text/x-log",
	"csv" => "text/csv",

	// images
	"png" => "image/png",
	"jpe" => "image/jpeg",
	"jpeg" => "image/jpeg",
	"jpg" => "image/jpeg",
	"gif" => "image/gif",
	"bmp" => "image/bmp",
	"ico" => "image/vnd.microsoft.icon",
	"tiff" => "image/tiff",
	"tif" => "image/tiff",
	"svg" => "image/svg+xml",
	"svgz" => "image/svg+xml",
	"webp" => "image/webp",

	// archives
	"zip" => "application/zip",
	"rar" => "application/x-rar-compressed",
	"exe" => "application/x-msdownload",
	"msi" => "application/x-msdownload",
	"cab" => "application/vnd.ms-cab-compressed",

	// audio/video
	"mp3" => "audio/mpeg",
	"qt" => "video/quicktime",
	"mov" => "video/quicktime",
	"mp4" => "video/mp4",
	"3gp" => "video/3gpp",
	"avi" => "video/x-msvideo",
	"wmv" => "video/x-ms-wmv",

	// adobe
	"pdf" => "application/pdf",
	"psd" => "image/vnd.adobe.photoshop",
	"ai" => "application/postscript",
	"eps" => "application/postscript",
	"ps" => "application/postscript",

	// ms office
	"doc" => "application/msword",
	"rtf" => "application/rtf",
	"xls" => "application/vnd.ms-excel",
	"ppt" => "application/vnd.ms-powerpoint",
	"docx" => "application/msword",
	"xlsx" => "application/vnd.ms-excel",
	"pptx" => "application/vnd.ms-powerpoint",

	// open office
	"odt" => "application/vnd.oasis.opendocument.text",
	"ods" => "application/vnd.oasis.opendocument.spreadsheet",
));

// Error Codes
define("TYPE_INT", "integer");
define("TYPE_FLOAT", "float");
define("TYPE_DOUBLE", "double");
define("TYPE_TEXT", "text");
define("TYPE_STRING", "string");
define("TYPE_RAW", "raw");
define("TYPE_JSON", "json");
define("TYPE_JSON_ASSOC", "jsonassoc");
define("TYPE_BOOL", "boolean");
define("TYPE_EMAIL", "email");
define("TYPE_USERNAME", "username");
define("TYPE_PHONE", "phone");
define("TYPE_SERIALIZED", "serialized");

define("SQL_SELECT", "SELECT");
define("SQL_INSERT", "INSERT");
define("SQL_UPDATE", "UPDATE");
define("SQL_DELETE", "DELETE");
define("SQL_CREATE", "CREATE");
define("SQL_TRUNCATE", "TRUNCATE");

define("UNKNOWN_ERROR", -1);
define("OK", 0);

define("ROUTE_NOT_FOUND", 100);
define("ROUTE_CALLBACK_ARGUMENTCOUNT_ERROR", 101);
define("ROUTE_CALLBACK_INVALID", 102);
define("NOT_IMPLEMENTED", 103);
define("API_NOT_FOUND", 104);
define("MISSING_PARAM", 105);
define("FILE_MISSING", 106);
define("USER_NOT_FOUND", 107);
define("MIDDLEWARE_CLASS_MISSING", 108);
define("MIDDLEWARE_CLASS_INVALID", 109);
define("AUTOLOAD_CLASS_MISSING", 110);
define("AUTOLOAD_CLASS_INVALID", 111);
define("ERROR_REPORT_NOT_FOUND", 112);
define("SQL_TABLE_NOT_FOUND", 113);
define("CLASS_NOT_FOUND", 114);
define("MIDDLEWARE_INVALID_RETURN", 115);
define("TEMPLATE_NOT_FOUND", 116);

define("DATA_TYPE_MISMATCH", 201);
define("INVALID_JSON", 202);
define("CODING_ERROR", 203);
define("DB_NOT_INITIALIZED", 204);
define("INVALID_VALUE", 205);
define("INVALID_FILE", 206);
define("INVALID_HASH", 207);
define("MAX_LENGTH_EXCEEDED", 208);
define("INVALID_URL", 209);
define("FILE_INSTANCE_NOT_FOUND", 209);
define("FILE_READ_ERROR", 210);
define("FILE_WRITE_ERROR", 211);
define("ROUTE_CALLBACK_INVALID_PARAM", 212);
define("ROUTE_INVALID_RESPONSE", 213);
define("HEADER_SENT", 214);
define("TEMPLATE_ILLEGAL_CALL", 215);
define("CONFIG_GROUP_DEFINED", 216);
define("CONFIG_ITEM_DEFINED", 217);
define("CONFIG_NO_ACTIVE_GROUP", 218);
define("CONFIG_ITEM_NOT_FOUND", 219);
define("INVALID_MOODLE_USER", 220);
define("FIELD_DATA_ERROR", 221);
define("INSTANCE_EXISTS", 222);
define("USER_NOT_IN_GROUP", 223);
define("INVALID_EXTENSION", 224);
define("INVALID_PROPERTY", 225);

define("ACCESS_DENIED", 300);
define("LOGGED_IN", 301);
define("INVALID_USERNAME", 302);
define("INVALID_PASSWORD", 303);
define("NOT_LOGGED_IN", 304);
define("USER_EXIST", 305);

define("SQL_ERROR", 400);
define("SQL_DRIVER_NOT_FOUND", 401);
define("INVALID_SQL_DRIVER", 402);
define("DATABASE_NOT_UPGRADED", 403);

define("INVALID_TOKEN", 500);
define("INVALID_SECRET", 501);
define("TOKEN_EXPIRED", 502);
