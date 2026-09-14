<?php

namespace Blink\Ether\Registry;

use Blink\Essence;
use Blink\Ether\ErrorPage\ErrorPageMiddleware;
use Blink\Ether\Event\EventMiddleware;
use Blink\Ether\Handler\HandlerMiddleware;
use Blink\Ether\Query\QueryMiddleware;
use Blink\Ether\Request\RequestMiddleware;
use Blink\Ether\Response\ResponseMiddleware;
use Blink\Ether\Route\RouteMiddleware;
use Blink\Exception\EtherInvalidMiddleware;
use function Blink\stringify;

/**
 * What Ether knows about every registered middleware: which contract it
 * implements, the alias it answers to, its priority, and the groups it belongs
 * to.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class MiddlewareRegistry {
	public const FAMILY_ROUTER = "router";

	public const FAMILY_QUERY = "query";

	public const FAMILY_ERRORPAGE = "errorpage";

	public const FAMILY_EVENT = "event";

	public const TYPE_ROUTE = "route";

	public const TYPE_REQUEST = "request";

	public const TYPE_RESPONSE = "response";

	public const TYPE_QUERY = "query";

	public const TYPE_ERRORPAGE = "errorpage";

	public const TYPE_EVENT = "event";

	public const TYPE_HANDLER = "handler";

	/** @var array<string, array{class: string, type: string, family: string, alias: ?string, priority: int}> */
	protected array $definitions = array();

	/** @var array<string, string> */
	protected array $aliases = array();

	/** @var array<string, array{name: string, family: string, references: string[]}> */
	protected array $groups = array();

	protected string $version = "";

	public function __construct(protected ?MiddlewareRegistryCache $cache = null) {
		$this -> cache = $cache ?? MiddlewareRegistryCache::make();
		$this -> load();
	}

	public static function make(): static {
		return Essence::make(static::class);
	}

	/**
	 * @param	class-string	$class
	 * @param	?string			$alias
	 * @param	?int			$priority
	 * @return	array{class: string, type: string, family: string, alias: ?string, priority: int}
	 */
	public function register(string $class, ?string $alias = null, ?int $priority = null): array {
		$definition = $this -> ensureDefinition($class, false);
		$previousAlias = $definition["alias"];
		$nextAlias = $alias ?? $previousAlias;
		$nextPriority = $priority ?? $definition["priority"];

		if ($alias !== null) {
			$this -> validateReferenceName($alias, "middleware alias");
			$this -> reserveAlias($alias, $class);
		}

		if (
			$definition["alias"] === $nextAlias
			&& $definition["priority"] === $nextPriority
			&& ($nextAlias === null || ($this -> aliases[$nextAlias] ?? null) === $class)
		)
			return $definition;

		$definition["alias"] = $nextAlias;
		$definition["priority"] = $nextPriority;

		if ($previousAlias !== null && $definition["alias"] !== $previousAlias)
			unset($this -> aliases[$previousAlias]);

		if ($definition["alias"] !== null)
			$this -> aliases[$definition["alias"]] = $class;

		$this -> definitions[$class] = $definition;
		$this -> store();
		return $definition;
	}

	/**
	 * @param	string		$group
	 * @param	string[]	$references
	 * @return	array{name: string, family: string, references: string[]}
	 */
	public function registerGroup(string $group, array $references): array {
		$this -> validateReferenceName($group, "middleware group");
		$this -> validateReferences($references, "middleware group");

		if (isset($this -> aliases[$group]))
			throw new EtherInvalidMiddleware(
				$group,
				"Middleware group <code>{$group}</code> conflicts with an existing middleware alias."
			);

		if (empty($references))
			throw new EtherInvalidMiddleware(
				$group,
				"Middleware group <code>{$group}</code> must contain at least one middleware reference."
			);

		$family = $this -> detectGroupFamily($references);
		$this -> assertGroupGraphIsAcyclic($group, $references, $family);

		if (isset($this -> groups[$group])) {
			$current = $this -> groups[$group];

			if ($current["family"] === $family && $current["references"] === array_values($references))
				return $current;
		}

		$this -> groups[$group] = array(
			"name" => $group,
			"family" => $family,
			"references" => array_values($references)
		);

		$this -> store();
		return $this -> groups[$group];
	}

	/**
	 * @param	string[]	$references
	 * @param	string		$type
	 */
	public function assertTypeReferences(array $references, string $type): void {
		$this -> validateReferences($references, "{$type} middleware");
		$family = static::familyFromType($type);

		foreach ($references as $reference) {
			if (isset($this -> groups[$reference])) {
				$group = $this -> groups[$reference];

				if ($group["family"] !== $family)
					throw new EtherInvalidMiddleware(
						$reference,
						"Middleware group <code>{$reference}</code> belongs to the <code>{$group['family']}</code> family and cannot be attached to the <code>{$type}</code> middleware stack."
					);

				if (empty($this -> resolveTypeReferences(array( $reference ), $type, true)))
					throw new EtherInvalidMiddleware(
						$reference,
						"Middleware group <code>{$reference}</code> does not contain any <code>{$type}</code> middleware."
					);

				continue;
			}

			$definition = $this -> resolveDefinitionReference($reference);

			if ($definition["type"] !== $type)
				throw new EtherInvalidMiddleware(
					$reference,
					"Middleware reference <code>{$reference}</code> resolves to <code>{$definition['type']}</code> middleware and cannot be attached to the <code>{$type}</code> middleware stack."
				);
		}
	}

	/**
	 * @param	string[]	$references
	 * @param	string		$family
	 */
	public function assertFamilyReferences(array $references, string $family): void {
		$this -> validateReferences($references, "{$family} middleware");

		foreach ($references as $reference) {
			if ($this -> getReferenceFamily($reference) !== $family)
				throw new EtherInvalidMiddleware(
					$reference,
					"Middleware reference <code>{$reference}</code> does not belong to the <code>{$family}</code> family."
				);
		}
	}

	/**
	 * @param	string[]	$references
	 * @param	string		$type
	 * @return	class-string[]
	 */
	public function resolveTypeReferences(array $references, string $type, bool $filterMismatched = false): array {
		$entries = array();
		$order = 0;

		foreach ($references as $reference)
			$this -> expandTypeReference($entries, $reference, $type, $filterMismatched, array(), $order);

		// Higher priority runs first; registration order breaks ties, so a
		// stack registered without priorities keeps the order it was written.
		usort($entries, function (array $a, array $b) {
			if ($a["priority"] === $b["priority"])
				return $a["order"] <=> $b["order"];

			return $b["priority"] <=> $a["priority"];
		});

		$resolved = array();

		foreach ($entries as $entry) {
			if (!in_array($entry["class"], $resolved, true))
				$resolved[] = $entry["class"];
		}

		return $resolved;
	}

	/**
	 * @return	array{
	 * 	definitions: array<string, array{class: string, type: string, family: string, alias: ?string, priority: int}>,
	 * 	aliases: array<string, string>,
	 * 	groups: array<string, array{name: string, family: string, references: string[]}>
	 * }
	 */
	public function snapshot(): array {
		return array(
			"definitions" => $this -> definitions,
			"aliases" => $this -> aliases,
			"groups" => $this -> groups
		);
	}

	public function purge(): void {
		$this -> definitions = array();
		$this -> aliases = array();
		$this -> groups = array();
		$this -> version = $this -> cache -> getVersion();
		$this -> cache -> purge();
	}

	protected function load(): void {
		$this -> version = $this -> cache -> getVersion();
		$cached = $this -> cache -> load($this -> version);

		if (!is_array($cached))
			return;

		$this -> definitions = is_array($cached["definitions"] ?? null)
			? $cached["definitions"]
			: array();

		$this -> aliases = is_array($cached["aliases"] ?? null)
			? $cached["aliases"]
			: array();

		$this -> groups = is_array($cached["groups"] ?? null)
			? $cached["groups"]
			: array();
	}

	protected function store(): void {
		$this -> cache -> store($this -> version, $this -> definitions, $this -> aliases, $this -> groups);
	}

	/**
	 * @param	class-string	$class
	 * @return	array{class: string, type: string, family: string, alias: ?string, priority: int}
	 */
	protected function ensureDefinition(string $class, bool $persist = true): array {
		if (isset($this -> definitions[$class]))
			return $this -> definitions[$class];

		$definition = $this -> inspectDefinition($class);
		$this -> definitions[$class] = $definition;

		if ($persist)
			$this -> store();

		return $definition;
	}

	/**
	 * @param	class-string	$class
	 * @return	array{class: string, type: string, family: string, alias: ?string, priority: int}
	 */
	protected function inspectDefinition(string $class): array {
		if (!class_exists($class))
			throw new EtherInvalidMiddleware(
				$class,
				"Middleware class <code>{$class}</code> does not exist or cannot be autoloaded."
			);

		$contracts = array(
			static::TYPE_ROUTE => is_subclass_of($class, RouteMiddleware::class),
			static::TYPE_REQUEST => is_subclass_of($class, RequestMiddleware::class),
			static::TYPE_RESPONSE => is_subclass_of($class, ResponseMiddleware::class),
			static::TYPE_QUERY => is_subclass_of($class, QueryMiddleware::class),
			static::TYPE_ERRORPAGE => is_subclass_of($class, ErrorPageMiddleware::class),
			static::TYPE_EVENT => is_subclass_of($class, EventMiddleware::class),
			static::TYPE_HANDLER => is_subclass_of($class, HandlerMiddleware::class)
		);

		$matches = array_keys(array_filter($contracts));

		if (empty($matches))
			throw new EtherInvalidMiddleware(
				$class,
				"Middleware class <code>{$class}</code> must implement one Ether middleware contract."
			);

		// Which stage a middleware belongs to is read off its contract, so a
		// class implementing two of them has no single answer.
		if (count($matches) !== 1)
			throw new EtherInvalidMiddleware(
				$class,
				"Middleware class <code>{$class}</code> must implement exactly one Ether middleware contract."
			);

		$type = $matches[0];

		return array(
			"class" => $class,
			"type" => $type,
			"family" => static::familyFromType($type),
			"alias" => null,
			"priority" => 0
		);
	}

	protected function reserveAlias(string $alias, string $class): void {
		if (isset($this -> groups[$alias]))
			throw new EtherInvalidMiddleware(
				$alias,
				"Middleware alias <code>{$alias}</code> conflicts with an existing middleware group."
			);

		if (isset($this -> aliases[$alias]) && $this -> aliases[$alias] !== $class)
			throw new EtherInvalidMiddleware(
				$alias,
				"Middleware alias <code>{$alias}</code> is already registered for a different middleware class."
			);
	}

	/**
	 * @param	string		$group
	 * @param	string[]	$references
	 */
	protected function assertGroupGraphIsAcyclic(string $group, array $references, string $family): void {
		$groups = $this -> groups;
		$groups[$group] = array(
			"name" => $group,
			"family" => $family,
			"references" => array_values($references)
		);

		$this -> walkGroupGraph($group, $groups, $references, array( $group ));
	}

	/**
	 * @param	array<string, array{name: string, family: string, references: string[]}> $groups
	 * @param	string[]	$references
	 * @param	string[]	$stack
	 */
	protected function walkGroupGraph(string $group, array $groups, array $references, array $stack): void {
		foreach ($references as $reference) {
			if (!isset($groups[$reference]))
				continue;

			if (in_array($reference, $stack, true)) {
				$cycle = implode(" -> ", array_merge($stack, array( $reference )));
				throw new EtherInvalidMiddleware(
					$group,
					"Circular Ether middleware group reference detected: <code>{$cycle}</code>."
				);
			}

			$this -> walkGroupGraph(
				$group,
				$groups,
				$groups[$reference]["references"],
				array_merge($stack, array( $reference ))
			);
		}
	}

	/**
	 * @param	string[]	$references
	 */
	protected function detectGroupFamily(array $references): string {
		$families = array();

		foreach ($references as $reference)
			$families[] = $this -> getReferenceFamily($reference);

		$families = array_values(array_unique($families));

		if (count($families) !== 1)
			throw new EtherInvalidMiddleware(
				stringify($references),
				"A middleware group has to stay within one family, since the stack it gets attached to only runs one."
			);

		return $families[0];
	}

	protected function getReferenceFamily(string $reference): string {
		if (isset($this -> groups[$reference]))
			return $this -> groups[$reference]["family"];

		return $this -> resolveDefinitionReference($reference)["family"];
	}

	/**
	 * @param	string	$reference
	 * @return	array{class: string, type: string, family: string, alias: ?string, priority: int}
	 */
	protected function resolveDefinitionReference(string $reference): array {
		if (isset($this -> aliases[$reference]))
			return $this -> definitions[$this -> aliases[$reference]];

		return $this -> ensureDefinition($reference);
	}

	/**
	 * @param	array<int, array{class: string, priority: int, order: int}> $entries
	 * @param	string		$reference
	 * @param	string		$type
	 * @param	string[]	$stack
	 */
	protected function expandTypeReference(array &$entries, string $reference, string $type, bool $filterMismatched, array $stack, int &$order): void {
		if (in_array($reference, $stack, true)) {
			$cycle = implode(" -> ", array_merge($stack, array( $reference )));
			throw new EtherInvalidMiddleware(
				$reference,
				"Circular Ether middleware reference detected: <code>{$cycle}</code>."
			);
		}

		if (isset($this -> groups[$reference])) {
			$group = $this -> groups[$reference];

			if ($group["family"] !== static::familyFromType($type)) {
				if ($filterMismatched)
					return;

				throw new EtherInvalidMiddleware(
					$reference,
					"Middleware group <code>{$reference}</code> belongs to the <code>{$group['family']}</code> family and cannot be resolved as <code>{$type}</code> middleware."
				);
			}

			foreach ($group["references"] as $nested)
				$this -> expandTypeReference($entries, $nested, $type, $filterMismatched, array_merge($stack, array( $reference )), $order);

			return;
		}

		$definition = $this -> resolveDefinitionReference($reference);

		if ($definition["type"] !== $type) {
			// A router group mixes route, request and response middleware, and
			// each stage only wants its own; the rest are skipped rather than
			// rejected.
			if ($filterMismatched)
				return;

			throw new EtherInvalidMiddleware(
				$reference,
				"Middleware reference <code>{$reference}</code> resolves to <code>{$definition['type']}</code> middleware and cannot be resolved as <code>{$type}</code> middleware."
			);
		}

		$entries[] = array(
			"class" => $definition["class"],
			"priority" => $definition["priority"],
			"order" => $order++
		);
	}

	/**
	 * @param	string[]	$references
	 * @param	string		$context
	 */
	protected function validateReferences(array $references, string $context): void {
		foreach ($references as $reference) {
			if (!is_string($reference) || empty($reference))
				throw new EtherInvalidMiddleware(
					stringify($reference),
					"{$context} entries must be non-empty strings."
				);
		}
	}

	protected function validateReferenceName(string $name, string $context): void {
		if (empty(trim($name)))
			throw new EtherInvalidMiddleware($name, "{$context} name cannot be empty.");
	}

	protected static function familyFromType(string $type): string {
		return match ($type) {
			static::TYPE_QUERY => static::FAMILY_QUERY,
			static::TYPE_ERRORPAGE => static::FAMILY_ERRORPAGE,
			static::TYPE_EVENT, static::TYPE_HANDLER => static::FAMILY_EVENT,
			default => static::FAMILY_ROUTER
		};
	}
}
