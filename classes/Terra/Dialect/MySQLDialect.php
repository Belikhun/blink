<?php

namespace Blink\Terra\Dialect;

use Blink\Terra\Column;
use Blink\Terra\Dialect;
use Blink\Terra\Table;

/**
 * Terra's MySQL and MariaDB dialect.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class MySQLDialect extends Dialect {
	public function compileCreate(Table $table): array {
		$definitions = array();

		foreach ($table -> columns as $column)
			$definitions[] = $this -> compileColumn($column);

		$primary = $this -> primaryColumns($table);

		if (!empty($primary)) {
			$columns = implode(", ", array_map(fn ($name) => $this -> wrap($name), $primary));
			$definitions[] = "PRIMARY KEY ({$columns})";
		}

		foreach ($table -> foreignKeys as $key)
			$definitions[] = $this -> compileForeignKey($key);

		$statements = array(
			"CREATE TABLE {$this -> wrap($table -> name)} (\n\t" . implode(",\n\t", $definitions)
				. "\n) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci"
		);

		// Indexes go out as their own statements rather than inline, so the
		// names match what the SQLite dialect produces for the same layer.
		foreach ($table -> indexes as $index)
			$statements[] = $this -> compileIndex($table -> name, $index);

		return $statements;
	}

	public function compileAlter(Table $table): array {
		$statements = array();

		foreach ($table -> renamedColumns as $from => $to)
			$statements[] = "ALTER TABLE {$this -> wrap($table -> name)} RENAME COLUMN {$this -> wrap($from)} TO {$this -> wrap($to)}";

		foreach ($table -> columns as $column) {
			$sql = "ALTER TABLE {$this -> wrap($table -> name)} ADD COLUMN " . $this -> compileColumn($column);

			if (!empty($column -> after))
				$sql .= " AFTER {$this -> wrap($column -> after)}";

			$statements[] = $sql;
		}

		foreach ($table -> changes as $column)
			$statements[] = "ALTER TABLE {$this -> wrap($table -> name)} MODIFY COLUMN " . $this -> compileColumn($column);

		foreach ($table -> droppedIndexes as $name)
			$statements[] = $this -> compileDropIndex($table -> name, $name);

		foreach ($table -> droppedColumns as $name)
			$statements[] = "ALTER TABLE {$this -> wrap($table -> name)} DROP COLUMN {$this -> wrap($name)}";

		foreach ($table -> indexes as $index)
			$statements[] = $this -> compileIndex($table -> name, $index);

		foreach ($table -> foreignKeys as $key)
			$statements[] = "ALTER TABLE {$this -> wrap($table -> name)} ADD " . $this -> compileForeignKey($key);

		return $statements;
	}

	public function compileDrop(string $table, bool $ifExists): string {
		return "DROP TABLE " . ($ifExists ? "IF EXISTS " : "") . $this -> wrap($table);
	}

	public function compileRename(string $from, string $to): string {
		return "RENAME TABLE {$this -> wrap($from)} TO {$this -> wrap($to)}";
	}

	public function compileDropAll(array $tables): array {
		if (empty($tables))
			return array();

		$wrapped = implode(", ", array_map(fn ($name) => $this -> wrap($name), $tables));

		// Constraints off for the duration, so the tables can go in any order.
		return array(
			"SET FOREIGN_KEY_CHECKS = 0",
			"DROP TABLE {$wrapped}",
			"SET FOREIGN_KEY_CHECKS = 1"
		);
	}

	public function compileDropIndex(string $table, string $name): string {
		return "DROP INDEX {$this -> wrap($name)} ON {$this -> wrap($table)}";
	}

	public function hasIndex(string $table, string $index): bool {
		global $DB;

		$rows = $DB -> raw(
			"SELECT 1 FROM information_schema.STATISTICS
				WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
				LIMIT 1",
			array( $table, $index )
		);

		return !empty($rows);
	}

	public function typeOf(Column $column): string {
		$unsigned = $column -> unsigned ? " UNSIGNED" : "";

		return match ($column -> type) {
			Table::TYPE_INTEGER => "INT{$unsigned}",
			Table::TYPE_BIG_INTEGER => "BIGINT{$unsigned}",
			Table::TYPE_TINY_INTEGER => "TINYINT{$unsigned}",
			Table::TYPE_BOOLEAN => "TINYINT(1)",
			Table::TYPE_FLOAT => "DOUBLE",
			Table::TYPE_DECIMAL => "DECIMAL({$column -> options['total']}, {$column -> options['places']})",
			Table::TYPE_STRING => "VARCHAR({$column -> options['length']})",
			Table::TYPE_TEXT => "TEXT",
			Table::TYPE_MEDIUM_TEXT => "MEDIUMTEXT",
			Table::TYPE_LONG_TEXT => "LONGTEXT",
			Table::TYPE_JSON => "JSON",

			// An epoch integer rather than a DATETIME, matching how blink
			// carries every timestamp it has.
			Table::TYPE_TIMESTAMP => "BIGINT",
			Table::TYPE_BINARY => "BLOB",
			default => $column -> type
		};
	}

	protected function compileColumn(Column $column): string {
		$sql = parent::compileColumn($column);

		if ($column -> autoIncrement)
			$sql .= " AUTO_INCREMENT";

		if (!empty($column -> comment))
			$sql .= " COMMENT " . $this -> quote($column -> comment);

		return $sql;
	}

	protected function compileForeignKey($key): string {
		$columns = implode(", ", array_map(fn ($name) => $this -> wrap($name), $key -> columns));
		$references = implode(", ", array_map(fn ($name) => $this -> wrap($name), $key -> references));

		$sql = "CONSTRAINT {$this -> wrap($key -> name)} FOREIGN KEY ({$columns})"
			. " REFERENCES {$this -> wrap($key -> table)} ({$references})";

		if (!empty($key -> onDelete))
			$sql .= " ON DELETE {$key -> onDelete}";

		if (!empty($key -> onUpdate))
			$sql .= " ON UPDATE {$key -> onUpdate}";

		return $sql;
	}

	/**
	 * @return	string[]
	 */
	protected function primaryColumns(Table $table): array {
		if (!empty($table -> primary))
			return $table -> primary;

		$primary = array();

		foreach ($table -> columns as $column) {
			if ($column -> primary)
				$primary[] = $column -> name;
		}

		return $primary;
	}
}
