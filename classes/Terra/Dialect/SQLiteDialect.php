<?php

namespace Blink\Terra\Dialect;

use Blink\DB\Database;
use Blink\Terra\Column;
use Blink\Terra\Dialect;
use Blink\Terra\Table;

/**
 * Terra's SQLite dialect.
 *
 * SQLite's `ALTER TABLE` does far less than MySQL's, and what it refuses it
 * refuses at runtime with a bare syntax error. Everything it cannot do is
 * caught here while compiling instead, so a layer fails before it has changed
 * half a schema.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class SQLiteDialect extends Dialect {
	public function compileCreate(Table $table): array {
		$definitions = array();
		$primary = $this -> primaryColumns($table);

		foreach ($table -> columns as $column) {
			$sql = $this -> compileColumn($column);

			// SQLite only makes a column an alias for the rowid, and so
			// auto-incrementing, when the key is declared inline on exactly
			// one INTEGER column.
			if ($column -> autoIncrement && count($primary) === 1 && $primary[0] === $column -> name) {
				$sql = "{$this -> wrap($column -> name)} INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL";
				$primary = array();
			}

			$definitions[] = $sql;
		}

		if (!empty($primary)) {
			$columns = implode(", ", array_map(fn ($name) => $this -> wrap($name), $primary));
			$definitions[] = "PRIMARY KEY ({$columns})";
		}

		foreach ($table -> foreignKeys as $key)
			$definitions[] = $this -> compileForeignKey($key);

		$statements = array(
			"CREATE TABLE {$this -> wrap($table -> name)} (\n\t" . implode(",\n\t", $definitions) . "\n)"
		);

		foreach ($table -> indexes as $index)
			$statements[] = $this -> compileIndex($table -> name, $index);

		return $statements;
	}

	public function compileAlter(Table $table): array {
		$statements = array();

		foreach ($table -> renamedColumns as $from => $to)
			$statements[] = "ALTER TABLE {$this -> wrap($table -> name)} RENAME COLUMN {$this -> wrap($from)} TO {$this -> wrap($to)}";

		foreach ($table -> columns as $column) {
			if ($column -> primary || $column -> autoIncrement) {
				$this -> reject(
					"adding the primary key column <code>{$column -> name}</code> to the existing table <code>{$table -> name}</code>",
					"SQLite can only declare a primary key while creating a table. Create the table with the key in place, or move the data into a new table."
				);
			}

			// A new column is filled with its default for every existing row,
			// and NOT NULL with no default leaves nothing to fill them with.
			if (!$column -> nullable && !$column -> hasDefault()) {
				$this -> reject(
					"adding the non-nullable column <code>{$column -> name}</code> with no default to <code>{$table -> name}</code>",
					"Give the column a default, or make it nullable and fill it in a later layer."
				);
			}

			// `after()` is accepted and ignored: SQLite always appends.
			$statements[] = "ALTER TABLE {$this -> wrap($table -> name)} ADD COLUMN " . $this -> compileColumn($column);
		}

		if (!empty($table -> changes)) {
			$names = implode(", ", array_map(fn ($column) => $column -> name, $table -> changes));

			$this -> reject(
				"changing the definition of <code>{$names}</code> on <code>{$table -> name}</code>",
				"SQLite cannot alter a column in place. Create a new table with the shape you want, copy the rows across, then drop the old one."
			);
		}

		foreach ($table -> droppedIndexes as $name)
			$statements[] = $this -> compileDropIndex($table -> name, $name);

		if (!empty($table -> droppedColumns)) {
			if (!static::canDropColumns()) {
				$names = implode(", ", $table -> droppedColumns);

				$this -> reject(
					"dropping <code>{$names}</code> from <code>{$table -> name}</code> on SQLite " . \SQLite3::version()["versionString"],
					"Dropping a column needs SQLite 3.35 or newer."
				);
			}

			foreach ($table -> droppedColumns as $name)
				$statements[] = "ALTER TABLE {$this -> wrap($table -> name)} DROP COLUMN {$this -> wrap($name)}";
		}

		foreach ($table -> indexes as $index)
			$statements[] = $this -> compileIndex($table -> name, $index);

		if (!empty($table -> foreignKeys)) {
			$this -> reject(
				"adding a foreign key to the existing table <code>{$table -> name}</code>",
				"SQLite can only declare a foreign key while creating a table."
			);
		}

		return $statements;
	}

	public function compileDrop(string $table, bool $ifExists): string {
		return "DROP TABLE " . ($ifExists ? "IF EXISTS " : "") . $this -> wrap($table);
	}

	public function compileRename(string $from, string $to): string {
		return "ALTER TABLE {$this -> wrap($from)} RENAME TO {$this -> wrap($to)}";
	}

	public function compileDropAll(array $tables): array {
		$statements = array( "PRAGMA foreign_keys = OFF" );

		foreach ($tables as $name)
			$statements[] = $this -> compileDrop($name, true);

		$statements[] = "PRAGMA foreign_keys = ON";
		return $statements;
	}

	public function hasIndex(string $table, string $index, Database $database): bool {
		$rows = $database -> raw(
			"SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ? LIMIT 1",
			array( $table, $index )
		);

		return !empty($rows);
	}

	public function typeOf(Column $column): string {
		return match ($column -> type) {
			Table::TYPE_INTEGER,
			Table::TYPE_BIG_INTEGER,
			Table::TYPE_TINY_INTEGER,
			Table::TYPE_BOOLEAN,
			Table::TYPE_TIMESTAMP => "INTEGER",

			Table::TYPE_FLOAT => "REAL",
			Table::TYPE_DECIMAL => "NUMERIC",

			Table::TYPE_STRING => "VARCHAR({$column -> options['length']})",
			Table::TYPE_TEXT,
			Table::TYPE_MEDIUM_TEXT,
			Table::TYPE_LONG_TEXT,
			Table::TYPE_JSON => "TEXT",

			Table::TYPE_BINARY => "BLOB",
			default => $column -> type
		};
	}

	/**
	 * SQLite gained `ALTER TABLE ... DROP COLUMN` in 3.35.
	 */
	public static function canDropColumns(): bool {
		return \SQLite3::version()["versionNumber"] >= 3035000;
	}

	protected function compileForeignKey($key): string {
		$columns = implode(", ", array_map(fn ($name) => $this -> wrap($name), $key -> columns));
		$references = implode(", ", array_map(fn ($name) => $this -> wrap($name), $key -> references));

		$sql = "FOREIGN KEY ({$columns}) REFERENCES {$this -> wrap($key -> table)} ({$references})";

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
