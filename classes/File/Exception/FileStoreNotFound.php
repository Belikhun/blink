<?php

namespace Blink\File\Exception;

use Blink\Exception\BaseException;

/**
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileStoreNotFound extends BaseException {
	public string $store;

	/**
	 * @param	string[]	$available
	 */
	public function __construct(string $store, array $available = array()) {
		$this -> store = $store;

		parent::__construct(
			FILE_STORE_NOT_FOUND,
			"No file store is registered as <code>{$store}</code>.",
			500,
			array( "store" => $store, "available" => $available ),
			!empty($available)
				? "Available: <code>" . implode("</code>, <code>", $available) . "</code>. Register another with <code>File::useStore()</code> from a module's <code>attach()</code>."
				: null
		);
	}
}
