<?php

namespace Blink\Ether\ErrorPage;

use Blink\ErrorPage\ContextGroup;
use Blink\ErrorPage\ContextItem;
use Blink\ErrorPage\ContextRenderer;
use Blink\ErrorPage\Instance;

/**
 * Error page middleware execution context.
 *
 * Wraps the report being built. Anything added here lands in
 * {@see Instance::$contexts}, which is serialized into the stored report and
 * rendered later by `error.php`.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ErrorPageContext {
	/**
	 * @param	Instance	$page			The error page being built.
	 * @param	?array		$data			The raw report payload (status, exception, ...).
	 * @param	bool		$debugging		Whether site debugging is enabled.
	 */
	public function __construct(
		public Instance $page,
		public ?array $data = null,
		public bool $debugging = false
	) {}

	/**
	 * Append an already-built context group to the page.
	 */
	public function addGroup(ContextGroup $group): ContextGroup {
		$this -> page -> contexts[] = $group;
		return $group;
	}

	/**
	 * Create a named context group and append it, returning it so the caller
	 * can chain {@see ContextGroup::add()} calls.
	 */
	public function group(string $name): ContextGroup {
		return $this -> addGroup(new ContextGroup($name));
	}

	public function findGroup(string $name): ?ContextGroup {
		foreach ($this -> page -> contexts as $group) {
			if ($group -> name === $name)
				return $group;
		}

		return null;
	}

	/**
	 * Build a key/value context item rendered with the default list renderer.
	 */
	public function listItem(string $id, string $label, $value, string $icon = "info"): ContextItem {
		$item = new ContextItem($id, $label, $value, $icon);
		$item -> setRenderer([ ContextRenderer::class, "list" ]);
		return $item;
	}

	public function hasException(): bool {
		return $this -> page -> exception() !== null;
	}
}
