<?php

namespace Blink;

use Blink\Exception\BaseException;
use Blink\Exception\FileWriteError;
use Blink\Exception\UnserializeError;

/**
 * FileIO.php
 * 
 * Simple file input/output.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileIO {
	private $maxTry = 20;
	public $stream;
	public $path;

	public function __construct(
		String $path,
		mixed $defaultData = "",
		String $defaultType = TYPE_TEXT
	) {
		$this -> path = $path;

		if (!file_exists($path))
			$this -> write($defaultData, $defaultType, "x");
	}

	public function fos(String $path, String $mode) {
		$dirname = dirname($path);

		// Create parent folder if not exist yet.
		if (!is_dir($dirname))
			mkdir($dirname, 0777, true);

		$this -> stream = fopen($path, $mode);

		if (!$this -> stream) {
			$e = error_get_last();
			throw new BaseException(
				8,
				"FileIO -> fos(): [". $e["type"] ."]: "
					. $e["message"] . " tại "
					. $e["file"] . " dòng ". $e["line"],
				500,
				$e
			);
		}
	}

	public function fcs() {
		fclose($this -> stream);
	}

	/**
	 *
	 * Read file
	 * type: text/json/serialize
	 * 
	 * @param	string	$type	File data type
	 * @return	string|array|object|mixed
	 *
	 */
	public function read($type = TYPE_TEXT) {
		if (file_exists($this -> path)) {
			$tries = 0;
			
			while (!is_readable($this -> path)) {
				$tries++;

				if ($tries >= $this -> maxTry) {
					throw new BaseException(
						46,
						"FileIO -> read(): Read Timeout: Không có quyền đọc file "
							. basename($this -> path) ." sau $tries lần thử",
						500,
						Array( "path" => $this -> path )
					);
				}
				
				usleep(200000);
			}
		}

		if (class_exists("\Blink\Metric\File"))
			$metric = new \Blink\Metric\File("r", $type, $this -> path);

		$this -> fos($this -> path, "r");
		$size = filesize($this -> path);

		$data = ($size > 0)
			? fread($this -> stream, $size)
			: null;

		$this -> fcs();

		if (isset($metric))
			$metric -> time(!empty($data) ? mb_strlen($data, "utf-8") : -1);

		switch ($type) {
			case TYPE_JSON:
				return safeJSONParsing($data, $this -> path);

			case TYPE_JSON_ASSOC:
				return safeJSONParsing($data, $this -> path, true);

			case TYPE_SERIALIZED: {
				// Temporary disable `NOTICE` error reporting
				// to try unserialize data without triggering `E_NOTICE`
				try {
					set_error_handler(null, 0);
					$content = (!empty($data)) ? unserialize($data) : false;
					restore_error_handler();
				} catch (\Throwable $e) {
					// Nothing to do here.
				}

				if ($content === false || $content === serialize(false)) {
					$e = error_get_last();

					if (empty($e)) {
						$e = Array(
							"message" => "Failed to unserialize data. No further information is provided.",
							"content" => $data
						);
					}

					throw new UnserializeError($this -> path, $e["message"], $e);
				}

				return $content;
			}
			
			default:
				return $data;
		}
	}

	/**
	 *
	 * Write data to file
	 * type: text/json/serialize
	 * 
	 * @param	string|array|object		$data		Data to write
	 * @param	string					$type		File data type
	 * @return
	 *
	 */
	public function write($data, String $type = TYPE_TEXT, String $mode = "w") {
		if (file_exists($this -> path)) {
			$tries = 0;
			
			while (!is_writable($this -> path)) {
				$tries++;

				if ($tries >= $this -> maxTry)
					throw new FileWriteError($this -> path, $tries);

				usleep(200000);
			}
		}

		if (class_exists("\Blink\Metric\File"))
			$metric = new \Blink\Metric\File($mode, $type, $this -> path);
		
		$this -> fos($this -> path, $mode);

		switch ($type) {
			case TYPE_JSON:
				$data = json_encode($data, JSON_PRETTY_PRINT);
				break;

			case TYPE_SERIALIZED:
				$data = serialize($data);
				break;
		}

		fwrite($this -> stream, $data);
		$this -> fcs();

		if (isset($metric))
			$metric -> time(mb_strlen($data, "utf-8"));

		return true;
	}
}
