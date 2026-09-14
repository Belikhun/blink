<?php
/**
 * Language strings for english.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

return array(
	"errorpage.openFile" => "Open File",

	"errorpage.classNotDefined.title" => "Class is not defined",
	"errorpage.classNotDefined.content" => "The file <b>{file}</b> was loaded, but it does not define <b>{class}</b>. "
		. "Check the spelling, or add the class if it is not there yet.",

	"errorpage.classNotFound.title" => "Class not found",
	"errorpage.classNotFound.content" => "Nothing could be loaded for <b>{class}</b>. The autoloader tried:",

	"errorpage.routeNotFound.title" => "Route does not exist",
	"errorpage.routeNotFound.content" => "This path is not handled by the server. "
		. "Contact an administrator if you believe this is a mistake.",

	"errorpage.invalidDefinition.title" => "Invalid class definition",
	"errorpage.invalidDefinition.content" => "<b>{class}</b> has to extend <b>{from}</b>.",

	"errorpage.moduleNotFound.title" => "Module not found",
	"errorpage.moduleNotFound.content" => "<b>{module}</b> is listed in <code>CONFIG::\$MODULES</code> but could not be "
		. "loaded. Check the class name, and that its file sits where the autoloader looks for it.",

	"errorpage.notLoggedIn.title" => "You are not signed in",
	"errorpage.notLoggedIn.content" => "This page needs an authenticated session. Sign in and try again.",

	"terra.notUpgraded" => "The database schema is behind: {applied} of {total} layers have been raised.",
	"terra.notUpgraded.details" => "Run <code>php core/blink terra:apply</code>, or wait for the process already applying them to finish."
);
