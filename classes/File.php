<?php

/**
 * File.php
 * 
 * File store interface.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;
use Blink\DB\Exception\TableNotFound;
use Blink\Exception\BaseException;
use Blink\Exception\FileInstanceNotFound;

class File {
	public static String $ROOT;

	public ?int $id;
	public String $hash;
	public String $filename;
	public String $extension;
	public String $mimetype;
	public int $size;
	public ?\User $author;
	public int $created;

	public function __construct(
		?int $id = null,
		String $hash = null,
		String $filename = null,
		String $extension = null,
		String $mimetype = null,
		int $size = null,
		?\User $author = null,
		int $created = 0
	) {
		$this -> id = $id;
		$this -> hash = $hash;
		$this -> filename = $filename;
		$this -> extension = $extension;
		$this -> mimetype = $mimetype;
		$this -> size = $size;
		$this -> author = $author;
		$this -> created = $created;
	}

	public function isValidID() {
		return (!empty($this -> id) && $this -> id > 0);
	}

	public function save() {
		global $DB;

		$record = Array(
			"hash" => $this -> hash,
			"filename" => $this -> filename,
			"extension" => $this -> extension,
			"mimetype" => $this -> mimetype,
			"size" => $this -> size,
			"author" => $this -> author ?-> id,
			"created" => $this -> created
		);

		if (\CONFIG::$FILE_STORE === FILE_STORE_FS) {
			$path = $this -> getStorePath() . ".info";

			if (!$this -> isValidID()) {
				// Just add a random ID to make it valid.
				$this -> id = $record["id"] = randBetween(10000, 99999);
			}

			filePut($path, json_encode($record));
		} else {
			if ($this -> isValidID()) {
				$record["id"] = $this -> id;
				$DB -> update("files", $record);
			} else {
				try {
					$this -> id = $DB -> insert("files", $record);
				} catch (TableNotFound $e) {
					// Try to init the table and try again.
					static::initDB();
					$this -> id = $DB -> insert("files", $record);
				}
			}
		}
	}
	
	public function getStorePath() {
		return self::$ROOT . "/{$this -> hash}";
	}

	/**
	 * Serve file with content range support.
	 * @param	bool	$download		Send file download information header
	 * @return	void
	 */
	public function serve(bool $download = false) {
		// Close all stream to guarantee we echoed directly to
		// output stream.
		while (ob_get_length())
			ob_end_clean();

		$fs = @fopen($this -> getStorePath(), "rb");

		$size   = filesize($this -> getStorePath());
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte
		$buffer = 1024 * 8;

		header("Content-Type: {$this -> mimetype}");
		header("Accept-Ranges: 0-$length");


		if ($download)
			header("Content-Disposition: attachment; filename=\"{$this -> filename}\"");

		if (isset($_SERVER["HTTP_RANGE"])) {
			$c_start = $start;
			$c_end   = $end;

			list(, $range) = explode("=", $_SERVER["HTTP_RANGE"], 2);

			if (strpos($range, ",") !== false) {
				header("HTTP/1.1 416 Requested Range Not Satisfiable");
				header("Content-Range: bytes $start-$end/$size");
				exit();
			}

			if ($range == "-") {
				$c_start = $size - substr($range, 1);
			} else {
				$range  = explode("-", $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}

			$c_end = ($c_end > $end) ? $end : $c_end;

			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
				header("HTTP/1.1 416 Requested Range Not Satisfiable");
				header("Content-Range: bytes $start-$end/$size");
				exit();
			}

			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1;
			fseek($fs, $start);
			header("HTTP/1.1 206 Partial Content");
		}

		header("Content-Range: bytes $start-$end/$size");
		header("Content-Length: ".$length);

		set_time_limit(0);
		while(!feof($fs) && ($p = ftell($fs)) <= $end) {
			if ($p + $buffer > $end)
				$buffer = $end - $p + 1;

			echo fread($fs, $buffer);
			flush();
		}

		fclose($fs);
		exit();
	}

	protected static function initDB() {
		global $DB;
		$DB -> execute(fileGet(CORE_ROOT . "/db/tables/files.sql"));
	}

	/**
	 * Get file by hash.
	 * @param	string	$hash
	 * @return	File
	 * @throws	FileInstanceNotFound
	 */
	public static function getByHash(String $hash): File {
		global $DB;

		if (\CONFIG::$FILE_STORE === FILE_STORE_FS) {
			$path = self::$ROOT . "/{$hash}.info";
			$content = fileGet($path);

			if (empty($content))
				throw new FileInstanceNotFound($hash);

			return self::processRecord(json_decode($content));
		}

		try {
			$record = $DB -> record("files", Array( "hash" => $hash ));
		} catch (TableNotFound $e) {
			// Try to init the table and try again.
			static::initDB();
			$record = $DB -> record("files", Array( "hash" => $hash ));
		}

		if (empty($record))
			throw new FileInstanceNotFound($hash);

		return self::processRecord($record);
	}

	/**
	 * Get file by ID. It's not recommend to use this function.
	 * This function will not be available when file store is {@link FILE_STORE_FS}
	 * @param	int		$id
	 * @return	File
	 * @throws	FileInstanceNotFound
	 */
	public static function getByID(int $id): File {
		global $DB;

		if (\CONFIG::$FILE_STORE !== FILE_STORE_DB)
			throw new BaseException(DB_NOT_INITIALIZED, "File -> getByID() is not available in FS mode.", 500);

		try {
			$record = $DB -> record("files", Array( "id" => $id ));
		} catch (TableNotFound $e) {
			// Try to init the table and try again.
			static::initDB();
			$record = $DB -> record("files", Array( "id" => $id ));
		}

		if (empty($record))
			throw new FileInstanceNotFound($id);

		return self::processRecord($record);
	}

	/**
	 * Process a file record from the DB
	 *
	 * @param	object	$record
	 * @return	File
	 */
	public static function processRecord($record) {
		return new self(
			$record -> id,
			$record -> hash,
			$record -> filename,
			$record -> extension,
			$record -> mimetype,
			$record -> size,
			!empty($record -> author)
				? \User::getByID($record -> author)
				: null,
			$record -> created
		);
	}

	/**
	 * Process a bunch of records object returned by DB
	 *
	 * @param	object[]	$records
	 * @return	File[]
	 */
	public static function processRecords($records) {
		$files = Array();

		foreach ($records as $record)
			$files[] = self::processRecord($record);

		return $files;
	}
}

File::$ROOT = &\CONFIG::$FILES_ROOT;
