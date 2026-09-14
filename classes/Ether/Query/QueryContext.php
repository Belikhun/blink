<?php

namespace Blink\Ether\Query;

use Blink\Query;

/**
 * Query middleware execution context.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class QueryContext {
	public const KIND_READ = "read";
	public const KIND_WRITE = "write";
	public const KIND_RAW = "raw";

	/**
	 * Generated SQL for the terminal operation.
	 *
	 * @var	?string
	 */
	public ?string $sql = null;

	/**
	 * Params passed to the SQL statement.
	 *
	 * @var	array
	 */
	public array $params = array();

	/**
	 * Result returned by downstream middleware or query execution.
	 *
	 * @var	mixed
	 */
	public mixed $result = null;

	public function __construct(
		public Query $query,
		public string $operation,
		public string $kind
	) {}
}
