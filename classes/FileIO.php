<?php

namespace Blink;

use Blink\Exception\BaseException;
use Blink\Exception\FileWriteError;
use Blink\Exception\UnserializeError;
use Blink\Metric\FileMetric;

/**
 * Simple file input/output interface.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class FileIO {
	const NO_DEFAULT = null;

	private $maxTry = 20;
	public $stream;
	public $path;
	public string $type = TYPE_TEXT;

	public function __construct(
		string $path,
		mixed $default = FileIO::NO_DEFAULT,
		string $type = TYPE_TEXT
	) {
		$this -> path = $path;
		$this -> type = $type;

		if (!file_exists($path) && $default !== static::NO_DEFAULT) {
			$data = $default;

			if (is_callable($default))
				$data = $default();

			$this -> write($data, $type, "x");
		}
	}

	public function fos(string $path, string $mode) {
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
	public function read($type = null) {
		if (empty($type))
			$type = $this -> type;
		
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
						array( "path" => $this -> path )
					);
				}
				
				usleep(200000);
			}
		}

		if (class_exists(FileMetric::class))
			$metric = new FileMetric("r", $type, $this -> path);

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
						$e = array(
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
	public function write($data, string $type = null, string $mode = "w") {
		if (empty($type))
			$type = $this -> type;

		if (file_exists($this -> path)) {
			$tries = 0;
			
			while (!is_writable($this -> path)) {
				$tries++;

				if ($tries >= $this -> maxTry)
					throw new FileWriteError($this -> path, $tries);

				usleep(200000);
			}
		}

		if (class_exists(FileMetric::class))
			$metric = new FileMetric($mode, $type, $this -> path);
		
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
