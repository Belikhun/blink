<?php

namespace Blink\DB;

/**
 * Class contain database column details.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ColumnInfo {
	public string $table;

	public string $column;

	public int $position = 0;

	public mixed $default = null;

	public bool $nullable = false;

	public ColumnType $type;

	public function __construct(string $table, string $column) {
		$this -> table = $table;
		$this -> column = $column;
	}

	public function __serialize() {
		return array(
			"table" => $this -> table,
			"column" => $this -> column,
			"position" => $this -> position,
			"default" => $this -> default,
			"nullable" => $this -> nullable,
			"type" => $this -> type
		);
	}

	public function __unserialize(array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}
