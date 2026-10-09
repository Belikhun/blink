<?php

namespace Blink\Metric;

/**
 * One SQL statement.
 *
 * The statement is recorded by shape rather than by instance: comments are
 * stripped and `IN (?, ?, ?)` lists collapse to one marker, so two runs of the
 * same query that differ only in how many ids they bind share a hash.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class QueryMetric extends MetricInstance {
	/**
	 * Bytes of SQL kept per recorded query. Longer text is cut with a marker.
	 */
	const MAX_QUERY_LENGTH = 4096;

	/**
	 * Bytes of serialized params kept per recorded query. Over this the params
	 * become a placeholder rather than a truncated blob nothing can parse.
	 */
	const MAX_PARAMS_LENGTH = 1024;

	/**
	 * Queries longer than this are not saved at all. Bulk inserts and layer
	 * runs dominate storage and carry no useful per-instance detail.
	 */
	const SKIP_QUERY_LENGTH = 32768;

	public string $query;

	public ?array $params = null;

	public string $hash;

	public string $command;

	public ?string $table = null;

	/**
	 * Whether the statement raised rather than returned. A failed query still
	 * took time and still belongs in the timeline, so it is recorded and
	 * marked rather than dropped.
	 */
	public bool $errored = false;

	public int $rows = -1;

	/**
	 * Name of the database instance the statement ran on.
	 */
	public string $database = \Blink\DB\Database::DEFAULT;

	/**
	 * Engine family of that instance.
	 */
	public string $family = "";

	public function __construct(string $query, array|object|null $params = null, ?\Blink\DB\Database $database = null) {
		parent::__construct();

		$database = $database ?? \Blink\DB\Database::default();
		$this -> database = $database -> name;
		$this -> family = $database -> family();

		$clean = trim($query);
		$clean = preg_replace('/^\s*(?:\/\*.*?\*\/\s*)+/s', '', $clean);
		$clean = preg_replace('/^\s*(?:--[^\n]*\n\s*)+/s', '', $clean ?? $query);
		$clean = static::collapsePlaceholders(trim($clean ?? $query));

		$this -> query = $clean;

		if (!empty($params))
			$this -> params = (array) $params;

		// A statement on a named instance hashes with the instance name, so
		// the same text run against two databases is two queries, while the
		// default's hashes stay as they were before there were instances.
		$hashSource = ($this -> database === \Blink\DB\Database::DEFAULT)
			? $this -> query
			: "{$this -> database}\n{$this -> query}";

		$this -> hash = substr(md5($hashSource), 0, 12);
		$this -> command = preg_match('/^\s*([a-z]+)/i', $this -> query, $match)
			? strtoupper($match[1])
			: "UNKNOWN";

		$this -> table = static::tableOf($this -> command, $this -> query);

		if (\Blink\Metric::captures(\Blink\Metric::QUERIES))
			\Blink\Metric::$queries[] = $this;
	}

	/**
	 * The table a statement is mostly about, when it can be read off the text.
	 */
	protected static function tableOf(string $command, string $query): ?string {
		$re = match ($command) {
			"UPDATE" => '/^UPDATE\s+[`"{\[]?([a-z0-9_]+)[`"}\]]?/i',
			"INSERT", "REPLACE" => '/^(?:INSERT|REPLACE)\s+INTO\s+[`"{\[]?([a-z0-9_]+)[`"}\]]?/i',
			"CREATE", "ALTER", "DROP" => '/\bTABLE\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?[`"{\[]?([a-z0-9_]+)[`"}\]]?/i',

			// `DELETE FROM t` and `DELETE t FROM t` both carry the table
			// after FROM, as does everything that reads.
			default => '/\bFROM\s+[`"{\[]?([a-z0-9_]+)[`"}\]]?/i'
		};

		return preg_match($re, $query, $match)
			? $match[1]
			: null;
	}

	public function time(int $rows): static {
		$this -> rows = $rows;
		$this -> time = microtime(true);
		return $this;
	}

	public function save(): ?array {
		if (strlen($this -> query) > static::SKIP_QUERY_LENGTH)
			return null;

		return array(
			"hash" => $this -> hash,
			"query" => static::truncate($this -> query, static::MAX_QUERY_LENGTH),
			"params" => $this -> saveParams(),
			"command" => $this -> command,
			"table_name" => $this -> table,
			"rows" => $this -> rows,
			"delta_start" => $this -> deltaStart,
			"duration" => $this -> getTime(),
			"errored" => $this -> errored ? 1 : 0
		);
	}

	/**
	 * `IN (?, ?, ?)` and `IN (:a, :b, :c)` become `IN (?**)`. The marker keeps
	 * a collapsed list visibly distinct from a genuine single-value `IN (?)`,
	 * which is left alone.
	 */
	protected static function collapsePlaceholders(string $query): string {
		return preg_replace(
			'/\bIN\s*\(\s*(?:\?|:[a-z0-9_]+|\$\d+)(?:\s*,\s*(?:\?|:[a-z0-9_]+|\$\d+))+\s*\)/is',
			'IN (?**)',
			$query
		) ?? $query;
	}

	protected function saveParams(): ?string {
		if (empty($this -> params))
			return null;

		$serialized = serialize($this -> params);

		if (strlen($serialized) > static::MAX_PARAMS_LENGTH)
			return sprintf("[truncated: %d params, %d bytes]", count($this -> params), strlen($serialized));

		return $serialized;
	}

	protected static function truncate(string $value, int $length): string {
		if (strlen($value) <= $length)
			return $value;

		return substr($value, 0, $length) . sprintf(" /* ...truncated, %d bytes total */", strlen($value));
	}

	public function __toString(): string {
		return sprintf("%5s %8s %16s %2d%s",
			$this -> timeFormat(),
			$this -> command,
			$this -> table ?? "",
			$this -> rows,
			$this -> errored ? " [errored]" : "");
	}
}
