<?php

namespace Blink\Trace;

use Blink\Attribute\Lazyload;
use Blink\Essence;
use Blink\Exception\CodingError;
use Blink\Exception\StringNotFound;
use Blink\Localization;
use Blink\Model;
use Blink\Model\UserBase;

/**
 * One row of the audit log.
 *
 * Storage is deliberately narrow: `mode` and `level` are codes, and their
 * names, labels and colors come from the registries on demand. The message
 * is a lang key when one exists for it, so what was recorded and how it is
 * shown can differ by language.
 *
 * @extends	parent<static>
 *
 * @property	?UserBase	$actor
 * @property	?array		$payload
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Entry extends Model {
	public static string $table = "traces";

	public static $fillables = array(
		"id",
		"actor" => "actor_id",
		"actorIP" => "actor_ip",
		"component",
		"scope",
		"field",
		"mode",
		"level",
		"message",
		"sourceClass" => "source_class",
		"sourceID" => "source_id",
		"targetClass" => "target_class",
		"targetID" => "target_id",
		"payload",
		"created"
	);

	public int $id;

	#[Lazyload("actor")]
	protected ?UserBase $__actor = null;

	public ?string $actorIP;

	public string $component;

	public ?string $scope;

	/**
	 * A short indexed key for the subject of the entry, so related entries
	 * can be found by exact match rather than by searching the payload.
	 */
	public ?string $field;

	public int $mode;

	public int $level;

	public string $message;

	public ?string $sourceClass;

	public ?int $sourceID;

	public ?string $targetClass;

	public ?int $targetID;

	#[Lazyload("payload")]
	protected ?array $__payload = null;

	public int $created;

	protected static function processField(string $name, $value) {
		return match ($name) {
			"actor" => (!empty($value))
				? static::resolveActor(intval($value))
				: null,
			"payload" => static::decodePayload($value),
			"mode", "level" => intval($value),
			default => parent::processField($name, $value)
		};
	}

	/**
	 * The user behind an actor id, or nobody when the application has no user
	 * class or the user is gone.
	 */
	protected static function resolveActor(int $userID): ?UserBase {
		try {
			$userClass = \Blink::userClass();
			return $userClass::getByID($userID, Model::IGNORE_MISSING) ?: null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	protected function saveField(string $name) {
		return match ($name) {
			"actor" => $this -> actor ?-> id,
			"payload" => static::encodePayload($this -> payload),
			default => parent::saveField($name)
		};
	}

	protected static function decodePayload($payload): ?array {
		if (!is_string($payload) || trim($payload) === "")
			return null;

		$decoded = json_decode($payload, true);

		return (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
			? $decoded
			: null;
	}

	protected static function encodePayload($payload): ?string {
		if ($payload === null)
			return null;

		if (is_string($payload)) {
			$payload = trim($payload);

			return ($payload === "")
				? null
				: $payload;
		}

		$encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($encoded === false)
			throw new CodingError("Unable to encode the trace payload.");

		return $encoded;
	}

	/**
	 * @return	array{code: int, name: string, label: string, color: string, component: string}
	 */
	public function getMode(): array {
		return Essence::make(ModeRegistry::class) -> get($this -> mode);
	}

	/**
	 * @return	array{code: int, name: string, label: string, color: string, component: string}
	 */
	public function getLevel(): array {
		return Essence::make(LevelRegistry::class) -> get($this -> level);
	}

	/**
	 * The message as a reader sees it: the lang string for the message key
	 * when there is one, otherwise the message itself, with `{name}`
	 * placeholders filled from the entry and its payload either way.
	 */
	public function getDisplayMessage(): string {
		$args = array();

		foreach ($this -> getDisplayMessageArgs() as $name => $value) {
			if ($value === null || is_array($value) || is_object($value))
				continue;

			$args[$name] = is_bool($value)
				? ($value ? "true" : "false")
				: (string) $value;
		}

		try {
			return Localization::string($this -> message, $args, strict: true);
		} catch (StringNotFound $e) {
			return preg_replace_callback(
				'/\{([a-zA-Z0-9_]+)\}/',
				fn ($match) => $args[$match[1]] ?? $match[0],
				$this -> message
			) ?? $this -> message;
		}
	}

	protected function getDisplayMessageArgs(): array {
		$mode = $this -> getMode();
		$level = $this -> getLevel();

		$source = $this -> sourceClass
			? static::humanizeClassName($this -> sourceClass)
			: null;

		$target = $this -> targetClass
			? static::humanizeClassName($this -> targetClass)
			: null;

		$defaults = array(
			"entryID" => $this -> id ?? null,
			"id" => $this -> targetID ?? $this -> sourceID ?? $this -> id ?? null,
			"actor" => $this -> getActorLabel() ?? __("trace.actor.system"),
			"actorIP" => $this -> actorIP,
			"component" => $this -> component,
			"scope" => $this -> scope,
			"field" => $this -> field,
			"mode" => $mode["name"],
			"modeCode" => $mode["code"],
			"modeLabel" => static::resolveMetaLabel($mode),
			"level" => $level["name"],
			"levelCode" => $level["code"],
			"levelLabel" => static::resolveMetaLabel($level),
			"source" => $source,
			"sourceClass" => $this -> sourceClass,
			"sourceID" => $this -> sourceID,
			"target" => $target,
			"targetClass" => $this -> targetClass,
			"targetID" => $this -> targetID,
			"model" => $target ?? $source,
			"created" => $this -> created
		);

		return array_replace($defaults, $this -> payload ?? array());
	}

	public function jsonSerialize(int $depth = -1) {
		$mode = $this -> getMode();
		$level = $this -> getLevel();

		return array(
			"id" => $this -> id ?? null,
			"actorIP" => $this -> actorIP,
			"actor" => $this -> actor,
			"component" => $this -> component,
			"scope" => $this -> scope,
			"field" => $this -> field,
			"mode" => $mode["code"],
			"modeMeta" => $mode,
			"level" => $level["code"],
			"levelMeta" => $level,
			"message" => $this -> message,
			"displayMessage" => $this -> getDisplayMessage(),
			"sourceClass" => $this -> sourceClass,
			"sourceID" => $this -> sourceID,
			"targetClass" => $this -> targetClass,
			"targetID" => $this -> targetID,
			"payload" => $this -> payload,
			"created" => $this -> created
		);
	}

	protected function getActorLabel(): ?string {
		$actor = $this -> actor;

		if (!($actor instanceof UserBase))
			return null;

		$label = trim($actor -> displayName());

		return ($label !== "")
			? $label
			: ($actor -> username ?? null);
	}

	/**
	 * A registry label is a lang key; show the string behind it, or the name
	 * when there is none.
	 */
	public static function resolveMetaLabel(array $meta): string {
		$label = $meta["label"] ?? null;

		if (empty($label))
			return (string) ($meta["name"] ?? $meta["code"] ?? "");

		try {
			return Localization::string($label, strict: true);
		} catch (StringNotFound $e) {
			return $label;
		}
	}

	/**
	 * `App\Model\PurchaseOrder` reads as `Purchase Order`.
	 */
	public static function humanizeClassName(string $className): string {
		$parts = explode("\\", trim($className, "\\"));
		$name = end($parts) ?: $className;

		return trim(preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name) ?? $name);
	}
}
