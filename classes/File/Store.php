<?php

namespace Blink\File;

/**
 * Where file content is kept.
 *
 * A store deals only in content, addressed by the hash of that content. What
 * a file is called, who uploaded it and when are the {@see \Blink\File}
 * model's business; a store never sees them. Two uploads of the same bytes
 * therefore ask the store to hold one thing, and it is only when the last row
 * referring to that hash goes that the store is asked to forget it.
 *
 * Implement this to keep content somewhere other than the local disk, and
 * register it with {@see \Blink\File::useStore()}.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
interface Store {
	/**
	 * What `CONFIG::$FILE_STORE` names this store.
	 */
	public function name(): string;

	public function exists(string $hash): bool;

	/**
	 * Take content under a hash.
	 *
	 * Content already held is left alone: it is the same bytes by definition,
	 * and rewriting it would only risk truncating something another row is
	 * reading.
	 *
	 * @param	string	$source		Path to the file to take.
	 * @param	bool	$move		Consume the source rather than copy it.
	 */
	public function put(string $hash, string $source, bool $move = false): void;

	/**
	 * The whole content, or null when the store does not hold it.
	 */
	public function get(string $hash): ?string;

	/**
	 * A read handle on the content, for serving something too large to hold in
	 * memory. The caller closes it.
	 *
	 * @return	resource|null
	 */
	public function stream(string $hash);

	/**
	 * Size in bytes, or -1 when the store does not hold it.
	 */
	public function size(string $hash): int;

	/**
	 * A local filesystem path for the content, when the store has one.
	 *
	 * A store that keeps content elsewhere returns null, and callers fall
	 * back to {@see Store::stream()}.
	 */
	public function path(string $hash): ?string;

	/**
	 * Drop the content. Called once no row refers to the hash any more.
	 *
	 * @return	bool	Whether anything was removed.
	 */
	public function forget(string $hash): bool;

	/**
	 * Every hash the store holds.
	 *
	 * What `file:prune` walks to find content no row refers to any more. A
	 * store that cannot enumerate returns an empty iterator, and pruning
	 * skips it.
	 *
	 * @return	iterable<string>
	 */
	public function hashes(): iterable;
}
