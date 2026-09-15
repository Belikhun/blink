<?php

namespace Blink;

use Blink\Attribute\Lazyload;
use Blink\Exception\FileInstanceNotFound;
use Blink\Exception\FileNotFound;
use Blink\File\Exception\FileContentMissing;
use Blink\File\Exception\FileStoreNotFound;
use Blink\File\LocalStore;
use Blink\File\Store;
use Blink\Model\UserBase;
use Blink\ProgressReporter;

/**
 * A stored file: one row per upload, one copy of the content.
 *
 * Content is addressed by its hash, so two uploads of the same bytes are held
 * once. Each upload still gets its own row, with its own name, uploader and
 * lifetime, and the content only goes when the last row referring to it does.
 *
 * ```php
 * $file = File::fromUpload($request -> file("avatar"));
 * $file -> content();
 * $file -> delete();
 * ```
 *
 * Where the content actually lives is the store's business; see
 * {@see \Blink\File\Store}.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * @extends		Model<File>
 *
 * @property	?UserBase	$author
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class File extends Model {
	public static string $table = "files";

	public static $fillables = array(
		"id",
		"hash",
		"filename",
		"extension",
		"mimetype",
		"size",
		"author" => "author_id",
		"created"
	);

	public int $id;

	/**
	 * The hash of the content, in {@see \CoreConfig::$FILE_HASH}. What the
	 * store keeps the bytes under, and what makes two identical uploads one
	 * copy on disk.
	 */
	public string $hash;

	/**
	 * The name this upload arrived with, extension included.
	 */
	public string $filename;

	public string $extension = "";

	public string $mimetype = "application/octet-stream";

	public int $size = 0;

	#[Lazyload("author")]
	protected ?UserBase $__author = null;

	public int $created = 0;

	/**
	 * Stores registered beyond the built-in one, by name.
	 *
	 * @var array<string, Store>
	 */
	protected static array $stores = array();

	protected static ?Store $store = null;

	/**
	 * The store named by `CONFIG::$FILE_STORE`.
	 *
	 * @throws	FileStoreNotFound
	 */
	public static function store(): Store {
		$name = strtolower(trim(\CONFIG::$FILE_STORE));

		if (static::$store !== null && static::$store -> name() === $name)
			return static::$store;

		if (isset(static::$stores[$name]))
			return static::$store = static::$stores[$name];

		if ($name === "local")
			return static::$store = new LocalStore();

		throw new FileStoreNotFound($name, array_merge(array( "local" ), array_keys(static::$stores)));
	}

	/**
	 * Register a store, so `CONFIG::$FILE_STORE` can name it.
	 *
	 * Call it from a module's `attach()`.
	 */
	public static function useStore(Store $store): void {
		static::$stores[strtolower($store -> name())] = $store;

		// The site may already be asking for this one by name.
		if (strtolower(trim(\CONFIG::$FILE_STORE)) === strtolower($store -> name()))
			static::$store = $store;
	}

	/**
	 * Forget the resolved store, so the next call reads the config again.
	 */
	public static function forgetStore(): void {
		static::$store = null;
	}

	protected static function processField(string $name, $value) {
		return match ($name) {
			"author" => (!empty($value))
				? static::resolveAuthor((int) $value)
				: null,
			default => parent::processField($name, $value)
		};
	}

	protected static function resolveAuthor(int $userID): ?UserBase {
		try {
			$userClass = \Blink::userClass();
			return $userClass::getByID($userID, Model::IGNORE_MISSING) ?: null;
		} catch (\Throwable $e) {
			// No user class registered, or the user is gone. Neither makes the
			// file itself unreadable.
			return null;
		}
	}

	protected function saveField(string $name) {
		return match ($name) {
			"author" => $this -> author ?-> id,
			default => parent::saveField($name)
		};
	}

	/**
	 * Whether the store still holds this file's content.
	 */
	public function exists(): bool {
		return static::store() -> exists($this -> hash);
	}

	/**
	 * The whole content.
	 *
	 * @throws	FileContentMissing
	 */
	public function content(): string {
		$content = static::store() -> get($this -> hash);

		if ($content === null)
			throw new FileContentMissing($this -> hash, $this -> id ?? null);

		return $content;
	}

	/**
	 * A read handle on the content. The caller closes it.
	 *
	 * @return	resource
	 * @throws	FileContentMissing
	 */
	public function stream() {
		$stream = static::store() -> stream($this -> hash);

		if ($stream === null)
			throw new FileContentMissing($this -> hash, $this -> id ?? null);

		return $stream;
	}

	/**
	 * A local path for the content, when the store has one. A store that keeps
	 * content elsewhere returns null and the caller streams instead.
	 */
	public function path(): ?string {
		return static::store() -> path($this -> hash);
	}

	/**
	 * How many rows refer to the same content, this one included.
	 */
	public function shared(): int {
		return static::query() -> where(static::col("hash"), $this -> hash) -> count();
	}

	/**
	 * Delete the row, and the content when nothing else refers to it.
	 *
	 * The content goes after the transaction has committed. A rollback can put
	 * the row back; it cannot put the bytes back.
	 */
	public function delete(?ProgressReporter $reporter = null) {
		$hash = $this -> hash;
		$result = parent::delete($reporter);

		if (!static::query() -> where(static::col("hash"), $hash) -> exists())
			static::store() -> forget($hash);

		return $result;
	}

	/**
	 * Take a file from the local filesystem into the store.
	 *
	 * @param	string		$source		Path to read.
	 * @param	?string		$name		What to call it. Defaults to the source's name.
	 * @param	?UserBase	$author		Who it belongs to. Defaults to the signed-in user.
	 * @param	bool		$move		Consume the source rather than copy it.
	 * @throws	FileNotFound
	 */
	public static function fromPath(
		string $source,
		?string $name = null,
		?UserBase $author = null,
		bool $move = false
	): static {
		if (!is_file($source))
			throw new FileNotFound($source);

		$name = $name ?? basename($source);
		$info = pathinfo($name);
		$extension = strtolower($info["extension"] ?? "");

		$hash = static::hashOf($source);
		$size = (int) (@filesize($source) ?: 0);

		// Content first: a row pointing at content the store never took is
		// worse than content no row points at, which `file:prune` can sweep.
		static::store() -> put($hash, $source, $move);

		$file = static::create(array(
			"hash" => $hash,
			"filename" => $name,
			"extension" => $extension,
			"mimetype" => static::mimetypeOf($source, $extension),
			"size" => $size,
			"author" => $author ?? (Session::$user instanceof UserBase ? Session::$user : null),
			"created" => time()
		));

		$file -> save();
		return $file;
	}

	/**
	 * Take the content of a string into the store.
	 */
	public static function fromContent(
		string $content,
		string $name,
		?UserBase $author = null
	): static {
		$temp = tempnam(sys_get_temp_dir(), "blink-");

		try {
			file_put_contents($temp, $content);
			return static::fromPath($temp, $name, $author, move: true);
		} finally {
			if (is_file($temp))
				@unlink($temp);
		}
	}

	/**
	 * Take an uploaded file into the store.
	 */
	public static function fromUpload(Http\UploadedFile $upload, ?UserBase $author = null): static {
		return $upload -> store($author);
	}

	/**
	 * The hash of a file, in the configured algorithm.
	 */
	public static function hashOf(string $path): string {
		$hash = @hash_file(\CONFIG::$FILE_HASH, $path);

		if ($hash === false)
			throw new FileNotFound($path);

		return $hash;
	}

	protected static function mimetypeOf(string $path, string $extension): string {
		// No charset: the type is stored and served as-is, and an image or a
		// zip has no character set to declare.
		$mimetype = ($extension !== "")
			? mime($extension, charset: "", default: null)
			: null;

		if (!empty($mimetype))
			return $mimetype;

		$detected = @mime_content_type($path);

		return ($detected !== false && $detected !== "")
			? $detected
			: "application/octet-stream";
	}

	/**
	 * The first file recorded with this content.
	 *
	 * Several rows may share a hash, since each upload keeps its own name and
	 * uploader. Use {@see File::allByHash()} when they all matter.
	 *
	 * @throws	FileInstanceNotFound
	 */
	public static function getByHash(string $hash): static {
		$file = static::query()
			-> where(static::col("hash"), $hash)
			-> sort(static::col("id"), "ASC")
			-> first();

		if (empty($file))
			throw new FileInstanceNotFound($hash);

		return $file;
	}

	/**
	 * Every row recorded with this content.
	 *
	 * @return static[]
	 */
	public static function allByHash(string $hash): array {
		return static::query()
			-> where(static::col("hash"), $hash)
			-> sort(static::col("id"), "ASC")
			-> all();
	}

	public function __toString(): string {
		return sprintf("[%s] %s (%s)",
			substr($this -> hash, 0, 12),
			$this -> filename,
			convertSize($this -> size));
	}
}
