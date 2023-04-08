<?php
/**
 * UploadedFile.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;
use Blink\Exception\FileInstanceNotFound;
use Blink\Exception\UploadedFileError;

class UploadedFile {
	/**
	 * The uploaded file name
	 * @var string
	 */
	public ?String $name = null;

	/**
	 * The uploaded file name, without extension.
	 * @var string
	 */
	public ?String $filename = null;

	/**
	 * The uploaded file extension
	 * @var string
	 */
	public ?String $extension = null;

	/**
	 * The file mimetype.
	 * @var string
	 */
	public ?String $type = null;

	/**
	 * The uploaded file size.
	 * @var string
	 */
	public ?int $size = null;

	private ?String $temp = null;

	private int $error = 0;

	public function __construct(Array $file) {
		$this -> name = $file["name"];
		$this -> type = $file["type"];
		$this -> size = $file["size"];
		$this -> temp = $file["tmp_name"];
		$this -> error = $file["error"];

		$info = pathinfo($this -> name);
		$this -> filename = $info["filename"];
		$this -> extension = $info["extension"];
	}

	/**
	 * Do some critical check before processing the uploaded file.
	 * @return bool
	 * @throws Exception\UploadedFileError
	 */
	protected function check(bool $throw = false): bool {
		if (!$throw)
			return $this -> error === UPLOAD_ERR_OK;

		$message = "An unknown error occured while processing uploaded file.";

		switch ($this -> error) {
			case UPLOAD_ERR_OK:
				return true;

			case UPLOAD_ERR_INI_SIZE:
				$size = ini_get("upload_max_filesize");
				$message = "The uploaded file exceeds the upload_max_filesize directive ({$size})";
				break;
				
			case UPLOAD_ERR_FORM_SIZE:
				$message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
				break;

			case UPLOAD_ERR_PARTIAL:
				$message = "The uploaded file was only partially uploaded";
				break;

			case UPLOAD_ERR_NO_FILE:
				$message = "No file was uploaded";
				break;

			case UPLOAD_ERR_NO_TMP_DIR:
				$message = "Missing a temporary folder";
				break;

			case UPLOAD_ERR_CANT_WRITE:
				$message = "Failed to write file to disk";
				break;

			case UPLOAD_ERR_EXTENSION:
				$message = "A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop.";
				break;
		}
			
		throw new UploadedFileError($this -> error, $message, 500);
	}

	public function save(): File {
		global $DB;

		$this -> check(true);
		$hash = hash_file("md5", $this -> temp);

		// Check if file is already exist. If so
		// we don"t need to create new record for it.
		try {
			return File::getByHash($hash);
		} catch (FileInstanceNotFound $e) {
			// We don't need to do anything further here.
		}

		$instance = new File(
			null,
			$hash,
			$this -> name,
			$this -> extension,
			mime_content_type($this -> temp),
			$this -> size,
			\Session::$user,
			time()
		);

		$instance -> save();
		move_uploaded_file($this -> temp, File::$ROOT . "/{$hash}");
		return $instance;
	}
}