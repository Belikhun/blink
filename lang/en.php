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

	"errorpage.etherInvalidMiddleware.title" => "Middleware reference is invalid",
	"errorpage.etherInvalidMiddleware.content" => "<b>{reference}</b> is not a middleware class, a registered alias or a group. "
		. "Register it with <code>Ether::register()</code> from a module's <code>attach()</code>, or check the spelling.",

	"errorpage.essenceBindingNotDefined.title" => "No binding for this abstract",
	"errorpage.essenceBindingNotDefined.content" => "<b>{abstract}</b> is an interface or abstract class, so Essence cannot build it on its own. "
		. "Bind it to a concrete class with <code>Essence::bind()</code> from a module's <code>define()</code>.",

	"errorpage.noUserClass.title" => "No user class is registered",
	"errorpage.noUserClass.content" => "Something needed the current user, and no module has said which class represents one. "
		. "Create a class extending <code>Blink\\Model\\UserBase</code> and register it with "
		. "<code>Blink::useUserClass()</code> from a module's <code>define()</code>.",

	"trace.level.debug" => "Debug",
	"trace.level.info" => "Info",
	"trace.level.notice" => "Notice",
	"trace.level.warning" => "Warning",
	"trace.level.error" => "Error",
	"trace.level.critical" => "Critical",
	"trace.level.alert" => "Alert",
	"trace.level.emergency" => "Emergency",

	"trace.mode.view" => "View",
	"trace.mode.create" => "Create",
	"trace.mode.update" => "Update",
	"trace.mode.delete" => "Delete",
	"trace.mode.report" => "Report",

	"trace.actor.system" => "the system",
	"trace.model.created" => "{model} #{id} was created by {actor}.",
	"trace.model.updated" => "{model} #{id} was updated by {actor}, {changeCount} field(s) changed.",
	"trace.model.deleted" => "{model} #{id} was deleted by {actor}.",

	"time.now" => "just now",
	"time.ago" => "{value} {unit} ago",
	"time.in" => "in {value} {unit}",
	"time.day.next" => "tomorrow",
	"time.day.last" => "yesterday",
	"time.year.next" => "next year",
	"time.year.last" => "last year",
	"time.unit.year" => "years",
	"time.unit.year.one" => "year",
	"time.unit.day" => "days",
	"time.unit.day.one" => "day",
	"time.unit.hour" => "hours",
	"time.unit.hour.one" => "hour",
	"time.unit.minute" => "minutes",
	"time.unit.minute.one" => "minute",
	"time.unit.second" => "seconds",
	"time.unit.second.one" => "second",

	"terra.notUpgraded" => "The database schema is behind: {applied} of {total} layers have been raised.",
	"terra.notUpgraded.details" => "Run <code>php core/blink terra:apply</code>, or wait for the process already applying them to finish."
);
