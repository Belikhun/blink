<?php

namespace Blink\Console\Command;

use Blink\Console;
use Blink\Console\Command;
use Blink\Console\Exception\InvalidInput;
use function Blink\filePut;
use function Blink\getRelativePath;

/**
 * Write an empty layer file, named and timestamped.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class TerraMake extends Command {
	public static string $usage = "terra:make {name : Layer name, in snake_case} {--table= : Scaffold a create for this table}";

	public static string $description = "Create a new schema layer";

	public function run() {
		$name = strtolower(trim($this -> argument("name")));
		$name = preg_replace("/[^a-z0-9]+/", "_", $name);
		$name = trim((string) $name, "_");

		if ($name === "")
			throw new InvalidInput("A layer name has to contain at least one letter or digit.");

		$directory = BASE_PATH . "/db/terra";

		if (!file_exists($directory))
			mkdir($directory, recursive: true);

		// The timestamp prefix is what orders the stack, so it has to be the
		// moment the file is made rather than anything read from the name.
		$identifier = date("Y_m_d_His") . "_" . $name;
		$path = "{$directory}/{$identifier}.php";

		if (file_exists($path))
			throw new InvalidInput("A layer already exists at " . getRelativePath($path) . ".");

		filePut($path, $this -> stub($this -> option("table")));

		$this -> success("Created " . getRelativePath($path));
		return Console::EXIT_OK;
	}

	protected function stub(?string $table): string {
		$body = !empty($table)
			? "\t\tSchema::create(\"{$table}\", function (Table \$table) {\n"
				. "\t\t\t\$table -> id();\n"
				. "\t\t\t\$table -> timestamps();\n"
				. "\t\t});"
			: "\t\t//";

		$drop = !empty($table)
			? "\t\tSchema::dropIfExists(\"{$table}\");"
			: "\t\t//";

		$lines = array(
			"<?php",
			"",
			"use Blink\\Terra\\Layer;",
			"use Blink\\Terra\\Schema;",
			"use Blink\\Terra\\Table;",
			"",
			"return new class extends Layer {",
			"\tpublic function raise(): void {",
			$body,
			"\t}",
			"",
			"\tpublic function drop(): void {",
			$drop,
			"\t}",
			"};",
			""
		);

		return implode("\r\n", $lines);
	}
}
