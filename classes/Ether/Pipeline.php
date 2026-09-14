<?php

namespace Blink\Ether;

use Blink\Essence;

/**
 * Runs a middleware stack around a destination.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Pipeline {
	/**
	 * @param	class-string[]				$middleware
	 * @param	object						$context
	 * @param	callable(object): mixed		$destination
	 * @return	mixed
	 */
	public static function run(array $middleware, object $context, callable $destination) {
		$next = static function (object $context) use ($destination) {
			return $destination($context);
		};

		// Built back to front so the first entry ends up outermost, which is
		// the order the stack reads in when it is registered.
		foreach (array_reverse($middleware) as $middlewareClass) {
			$next = static function (object $context) use ($middlewareClass, $next) {
				$middleware = Essence::make($middlewareClass);

				$callNext = static function (?object $nextContext = null) use ($next, $context) {
					return $next($nextContext ?? $context);
				};

				return Essence::call(
					[ $middleware, "handle" ],
					args: array(
						"context" => $context,
						"next" => $callNext
					),
					binds: array(
						get_class($context) => $context,
						"callable" => $callNext
					)
				);
			};
		}

		return $next($context);
	}
}
