<?php

namespace Blink;

use Blink\Ether\ErrorPage\ErrorPageContext;
use Blink\Ether\ErrorPage\ErrorPageMiddleware;
use Blink\Ether\Event\EventDispatchContext;
use Blink\Ether\Event\EventMiddleware;
use Blink\Ether\Handler\HandlerInvocationContext;
use Blink\Ether\Handler\HandlerMiddleware;
use Blink\Ether\Pipeline;
use Blink\Ether\Query\QueryContext as EtherQueryContext;
use Blink\Ether\Query\QueryMiddleware as EtherQueryMiddleware;
use Blink\Ether\Registry\MiddlewareRegistry;
use Blink\Ether\Request\RequestContext;
use Blink\Ether\Request\RequestMiddleware;
use Blink\Ether\Response\ResponseContext;
use Blink\Ether\Response\ResponseMiddleware;
use Blink\Ether\Route\RouteContext;
use Blink\Ether\Route\RouteMiddleware;
use Blink\Exception\EtherInvalidMiddleware;
use Blink\Http\Response;
use Blink\Http\Router\Route;

/**
 * Blink's middleware stacks and the dispatcher that runs them.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Ether {
	/**
	 * Global route middleware references.
	 *
	 * @var	string[]
	 */
	protected static array $routeMiddleware = array();

	/**
	 * Global request middleware references.
	 *
	 * @var	string[]
	 */
	protected static array $requestMiddleware = array();

	/**
	 * Global response middleware references.
	 *
	 * @var	string[]
	 */
	protected static array $responseMiddleware = array();

	/**
	 * Global query middleware references.
	 *
	 * @var	string[]
	 */
	protected static array $queryMiddleware = array();

	/**
	 * Global error page middleware references.
	 *
	 * @var	string[]
	 */
	protected static array $errorPageMiddleware = array();

	/**
	 * Global event dispatch middleware references.
	 *
	 * @var	string[]
	 */
	protected static array $eventMiddleware = array();

	/**
	 * Global handler invocation middleware references.
	 *
	 * @var	string[]
	 */
	protected static array $handlerMiddleware = array();

	/**
	 * @var	?MiddlewareRegistry
	 */
	protected static ?MiddlewareRegistry $registry = null;

	/**
	 * Record a middleware's alias and priority without attaching it to a stack.
	 *
	 * @param	class-string	$class
	 * @param	?string			$alias
	 * @param	?int			$priority
	 * @return	string			The reference to use from here on.
	 */
	public static function register(string $class, ?string $alias = null, ?int $priority = null): string {
		$metadata = static::registry() -> register($class, $alias, $priority);
		return $metadata["alias"] ?? $metadata["class"];
	}

	/**
	 * Register a middleware and attach it to the stack its contract names.
	 *
	 * @param	class-string	$class
	 * @param	?string			$alias
	 * @param	?int			$priority
	 * @return	string			The reference to use from here on.
	 */
	public static function use(string $class, ?string $alias = null, ?int $priority = null): string {
		$metadata = static::registry() -> register($class, $alias, $priority);
		$reference = $metadata["alias"] ?? $metadata["class"];

		switch ($metadata["type"]) {
			case MiddlewareRegistry::TYPE_ROUTE:
				static::route($reference);
				break;

			case MiddlewareRegistry::TYPE_REQUEST:
				static::request($reference);
				break;

			case MiddlewareRegistry::TYPE_RESPONSE:
				static::response($reference);
				break;

			case MiddlewareRegistry::TYPE_QUERY:
				static::query($reference);
				break;

			case MiddlewareRegistry::TYPE_ERRORPAGE:
				static::errorPage($reference);
				break;

			case MiddlewareRegistry::TYPE_EVENT:
				static::event($reference);
				break;

			case MiddlewareRegistry::TYPE_HANDLER:
				static::handler($reference);
				break;
		}

		return $reference;
	}

	/**
	 * Name a set of middleware references so a route can attach them together.
	 *
	 * @param	string		$group
	 * @param	string		...$middleware
	 */
	public static function group(string $group, string ...$middleware): void {
		static::registry() -> registerGroup($group, $middleware);
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function route(string ...$middleware): void {
		static::appendStageMiddleware(static::$routeMiddleware, $middleware, MiddlewareRegistry::TYPE_ROUTE, "route middleware");
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function request(string ...$middleware): void {
		static::appendStageMiddleware(static::$requestMiddleware, $middleware, MiddlewareRegistry::TYPE_REQUEST, "request middleware");
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function response(string ...$middleware): void {
		static::appendStageMiddleware(static::$responseMiddleware, $middleware, MiddlewareRegistry::TYPE_RESPONSE, "response middleware");
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function query(string ...$middleware): void {
		static::appendStageMiddleware(static::$queryMiddleware, $middleware, MiddlewareRegistry::TYPE_QUERY, "query middleware");
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function errorPage(string ...$middleware): void {
		static::appendStageMiddleware(static::$errorPageMiddleware, $middleware, MiddlewareRegistry::TYPE_ERRORPAGE, "error page middleware");
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function event(string ...$middleware): void {
		static::appendStageMiddleware(static::$eventMiddleware, $middleware, MiddlewareRegistry::TYPE_EVENT, "event middleware");
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function handler(string ...$middleware): void {
		static::appendStageMiddleware(static::$handlerMiddleware, $middleware, MiddlewareRegistry::TYPE_HANDLER, "handler middleware");
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function assertRouteMiddleware(string ...$middleware): void {
		static::registry() -> assertTypeReferences($middleware, MiddlewareRegistry::TYPE_ROUTE);
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function assertRequestMiddleware(string ...$middleware): void {
		static::registry() -> assertTypeReferences($middleware, MiddlewareRegistry::TYPE_REQUEST);
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function assertResponseMiddleware(string ...$middleware): void {
		static::registry() -> assertTypeReferences($middleware, MiddlewareRegistry::TYPE_RESPONSE);
	}

	/**
	 * Assert references belong to the router family, which spans the route,
	 * request and response stages.
	 *
	 * @param	string	...$middleware
	 */
	public static function assertRouterLifecycleMiddleware(string ...$middleware): void {
		static::registry() -> assertFamilyReferences($middleware, MiddlewareRegistry::FAMILY_ROUTER);
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function assertQueryMiddleware(string ...$middleware): void {
		static::registry() -> assertTypeReferences($middleware, MiddlewareRegistry::TYPE_QUERY);
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function assertErrorPageMiddleware(string ...$middleware): void {
		static::registry() -> assertTypeReferences($middleware, MiddlewareRegistry::TYPE_ERRORPAGE);
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function assertEventMiddleware(string ...$middleware): void {
		static::registry() -> assertTypeReferences($middleware, MiddlewareRegistry::TYPE_EVENT);
	}

	/**
	 * @param	string	...$middleware
	 */
	public static function assertHandlerMiddleware(string ...$middleware): void {
		static::registry() -> assertTypeReferences($middleware, MiddlewareRegistry::TYPE_HANDLER);
	}

	/**
	 * Assert references belong to the event family, which spans the dispatch
	 * and handler stages.
	 *
	 * @param	string	...$middleware
	 */
	public static function assertEventFamilyMiddleware(string ...$middleware): void {
		static::registry() -> assertFamilyReferences($middleware, MiddlewareRegistry::FAMILY_EVENT);
	}

	/**
	 * @return	class-string<RouteMiddleware>[]
	 */
	public static function getRouteMiddleware(): array {
		return static::registry() -> resolveTypeReferences(static::$routeMiddleware, MiddlewareRegistry::TYPE_ROUTE, true);
	}

	/**
	 * @return	class-string<RequestMiddleware>[]
	 */
	public static function getRequestMiddleware(): array {
		return static::registry() -> resolveTypeReferences(static::$requestMiddleware, MiddlewareRegistry::TYPE_REQUEST, true);
	}

	/**
	 * @return	class-string<ResponseMiddleware>[]
	 */
	public static function getResponseMiddleware(): array {
		return static::registry() -> resolveTypeReferences(static::$responseMiddleware, MiddlewareRegistry::TYPE_RESPONSE, true);
	}

	/**
	 * @return	class-string<EtherQueryMiddleware>[]
	 */
	public static function getQueryMiddleware(): array {
		return static::registry() -> resolveTypeReferences(static::$queryMiddleware, MiddlewareRegistry::TYPE_QUERY, true);
	}

	/**
	 * @return	class-string<ErrorPageMiddleware>[]
	 */
	public static function getErrorPageMiddleware(): array {
		return static::registry() -> resolveTypeReferences(static::$errorPageMiddleware, MiddlewareRegistry::TYPE_ERRORPAGE, true);
	}

	/**
	 * @return	class-string<EventMiddleware>[]
	 */
	public static function getEventMiddleware(): array {
		return static::registry() -> resolveTypeReferences(static::$eventMiddleware, MiddlewareRegistry::TYPE_EVENT, true);
	}

	/**
	 * @return	class-string<HandlerMiddleware>[]
	 */
	public static function getHandlerMiddleware(): array {
		return static::registry() -> resolveTypeReferences(static::$handlerMiddleware, MiddlewareRegistry::TYPE_HANDLER, true);
	}

	/**
	 * Drop every runtime stack and the cached registry metadata behind them.
	 */
	public static function clear(): void {
		static::$routeMiddleware = array();
		static::$requestMiddleware = array();
		static::$responseMiddleware = array();
		static::$queryMiddleware = array();
		static::$errorPageMiddleware = array();
		static::$eventMiddleware = array();
		static::$handlerMiddleware = array();

		if (static::$registry !== null)
			static::$registry -> purge();
		else
			MiddlewareRegistry::make() -> purge();

		static::$registry = null;
	}

	/**
	 * Dispatch route middleware before route resolution.
	 *
	 * @param	callable(RouteContext): mixed	$destination
	 * @return	mixed
	 */
	public static function dispatchRoute(RouteContext $context, callable $destination) {
		return Pipeline::run(
			static::resolveMiddleware(static::$routeMiddleware, array(), array(), MiddlewareRegistry::TYPE_ROUTE),
			$context,
			$destination
		);
	}

	/**
	 * Dispatch request middleware for a matched route.
	 *
	 * @param	callable(RequestContext): mixed	$destination
	 * @return	mixed
	 */
	public static function dispatchRequest(Route $route, RequestContext $context, callable $destination) {
		$context -> result = Pipeline::run(
			static::resolveMiddleware(
				static::$requestMiddleware,
				$route -> getMiddleware(),
				$route -> getExcludedMiddleware(),
				MiddlewareRegistry::TYPE_REQUEST
			),
			$context,
			$destination
		);

		return $context -> result;
	}

	/**
	 * Dispatch response middleware after a response has been created.
	 *
	 * @param	callable(ResponseContext): mixed	$destination
	 * @return	Response
	 */
	public static function dispatchResponse(Route $route, ResponseContext $context, callable $destination): Response {
		$result = Pipeline::run(
			static::resolveMiddleware(
				static::$responseMiddleware,
				$route -> getMiddleware(),
				$route -> getExcludedMiddleware(),
				MiddlewareRegistry::TYPE_RESPONSE
			),
			$context,
			$destination
		);

		if ($result instanceof Response)
			$context -> response = $result;

		return $context -> response;
	}

	/**
	 * Dispatch query middleware for a query.
	 *
	 * @param	callable(EtherQueryContext): mixed	$destination
	 * @return	mixed
	 */
	public static function dispatchQuery(Query $query, EtherQueryContext $context, callable $destination) {
		$context -> result = Pipeline::run(
			static::resolveMiddleware(
				static::$queryMiddleware,
				$query -> getMiddleware(),
				$query -> getExcludedMiddleware(),
				MiddlewareRegistry::TYPE_QUERY
			),
			$context,
			$destination
		);

		return $context -> result;
	}

	/**
	 * Dispatch error page middleware while a report is being built.
	 *
	 * @param	callable(ErrorPageContext): mixed	$destination
	 * @return	ErrorPageContext
	 */
	public static function dispatchErrorPage(ErrorPageContext $context, callable $destination): ErrorPageContext {
		Pipeline::run(
			static::registry() -> resolveTypeReferences(static::$errorPageMiddleware, MiddlewareRegistry::TYPE_ERRORPAGE, true),
			$context,
			$destination
		);

		return $context;
	}

	/**
	 * Dispatch event middleware around a single Lightrail emission.
	 *
	 * @param	callable(EventDispatchContext): mixed	$destination
	 * @return	mixed
	 */
	public static function dispatchEvent(EventDispatchContext $context, callable $destination) {
		$middleware = static::resolveMiddleware(static::$eventMiddleware, array(), array(), MiddlewareRegistry::TYPE_EVENT);

		// Lightrail emits at high volume, so an empty stack skips the pipeline
		// entirely and the common case costs no more than a direct call.
		if (empty($middleware)) {
			$context -> result = $destination($context);
			return $context -> result;
		}

		$context -> result = Pipeline::run($middleware, $context, $destination);
		return $context -> result;
	}

	/**
	 * Dispatch handler middleware around a single Lightrail handler call.
	 *
	 * @param	callable(HandlerInvocationContext): mixed	$destination
	 * @param	string[]									$local
	 * @param	string[]									$excluded
	 * @return	mixed
	 */
	public static function dispatchHandler(HandlerInvocationContext $context, callable $destination, array $local = array(), array $excluded = array()) {
		$middleware = static::resolveMiddleware(static::$handlerMiddleware, $local, $excluded, MiddlewareRegistry::TYPE_HANDLER);

		if (empty($middleware)) {
			$context -> result = $destination($context);
			return $context -> result;
		}

		$context -> result = Pipeline::run($middleware, $context, $destination);
		return $context -> result;
	}

	/**
	 * @param	string[]	$registered
	 * @param	string[]	$local
	 * @param	string[]	$excluded
	 * @param	string		$type
	 * @return	string[]
	 */
	protected static function resolveMiddleware(array $registered, array $local, array $excluded, string $type): array {
		$global = static::registry() -> resolveTypeReferences($registered, $type, true);
		$local = static::registry() -> resolveTypeReferences($local, $type, true);
		$excluded = static::registry() -> resolveTypeReferences($excluded, $type, true);

		return array_values(array_filter(
			static::mergeMiddleware($global, $local),
			static fn (string $class) => !in_array($class, $excluded, true)
		));
	}

	/**
	 * @param	string[]	$registered
	 * @param	string[]	$middleware
	 * @param	string		$type
	 * @param	string		$context
	 */
	protected static function appendStageMiddleware(array &$registered, array $middleware, string $type, string $context): void {
		static::validateReferences($middleware, $context);
		static::registry() -> assertTypeReferences($middleware, $type);

		foreach ($middleware as $reference) {
			if (!in_array($reference, $registered, true))
				$registered[] = $reference;
		}
	}

	/**
	 * @param	string[]	$references
	 * @param	string		$context
	 */
	protected static function validateReferences(array $references, string $context): void {
		foreach ($references as $reference) {
			if (!is_string($reference) || empty($reference))
				throw new EtherInvalidMiddleware(
					stringify($reference),
					"{$context} entries must be non-empty strings."
				);
		}
	}

	protected static function registry(): MiddlewareRegistry {
		if (static::$registry === null)
			static::$registry = MiddlewareRegistry::make();

		return static::$registry;
	}

	/**
	 * @param	string[]	...$groups
	 * @return	string[]
	 */
	protected static function mergeMiddleware(array ...$groups): array {
		$resolved = array();

		foreach ($groups as $group) {
			foreach ($group as $class) {
				if (!in_array($class, $resolved, true))
					$resolved[] = $class;
			}
		}

		return $resolved;
	}
}
