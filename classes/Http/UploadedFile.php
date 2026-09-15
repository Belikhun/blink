<?php

namespace Blink\Http;

use Blink\File;
use Blink\Http\Exception\UploadedFileError;
use Blink\Model\UserBase;
use Blink\Session;
use function Blink\convertSize;
use function Blink\mime;

/**
 * A file in the request form, before anything has been done with it.
 *
 * It lives in PHP's upload directory and is discarded when the request ends.
 * {@see UploadedFile::store()} is what moves it somewhere permanent.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class UploadedFile {
	/**
	 * The name the file arrived with, extension included.
	 */
	public ?string $name = null;

	/**
	 * The name without its extension.
	 */
	public ?string $filename = null;

	public ?string $extension = null;

	/**
	 * The type the client claimed. Not to be trusted for anything that
	 * matters; {@see UploadedFile::detectedType()} reads the content.
	 */
	public ?string $type = null;

	public ?int $size = null;

	private ?string $temp = null;

	private int $error = 0;

	public function __construct(array $file) {
		$this -> name = $file["name"];
		$this -> type = $file["type"];
		$this -> size = $file["size"];
		$this -> temp = $file["tmp_name"];
		$this -> error = $file["error"];

		$info = pathinfo((string) $this -> name);
		$this -> filename = $info["filename"] ?? null;
		$this -> extension = isset($info["extension"])
			? strtolower($info["extension"])
			: null;
	}

	/**
	 * Whether the upload arrived intact.
	 *
	 * @throws	UploadedFileError	when `$throw`, with what went wrong.
	 */
	public function check(bool $throw = false): bool {
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

	/**
	 * The type read from the content, rather than the one the client claimed.
	 */
	public function detectedType(): ?string {
		if (empty($this -> temp) || !is_file($this -> temp))
			return null;

		$detected = @mime_content_type($this -> temp);

		return ($detected === false || $detected === "")
			? null
			: $detected;
	}

	/**
	 * Whether this is an image the site accepts: an allowed extension, within
	 * the size cap, and content that actually reads as an image.
	 */
	public function isImage(): bool {
		if (!$this -> check() || !in_array($this -> extension, \CONFIG::$IMAGE_ALLOW, true))
			return false;

		if ($this -> size > \CONFIG::$IMAGE_SIZE)
			return false;

		return str_starts_with((string) $this -> detectedType(), "image/");
	}

	/**
	 * Store this upload as an image, refusing anything that is not one.
	 *
	 * @throws	UploadedFileError
	 */
	public function image(?UserBase $author = null): File {
		$this -> check(true);

		if (!in_array($this -> extension, \CONFIG::$IMAGE_ALLOW, true)) {
			throw new UploadedFileError(
				UPLOAD_ERR_EXTENSION,
				"Images may not be <code>{$this -> extension}</code> files. Accepted: " . implode(", ", \CONFIG::$IMAGE_ALLOW),
				400
			);
		}

		if ($this -> size > \CONFIG::$IMAGE_SIZE) {
			throw new UploadedFileError(
				UPLOAD_ERR_FORM_SIZE,
				"The image is " . convertSize((int) $this -> size) . ", over the " . convertSize(\CONFIG::$IMAGE_SIZE) . " limit.",
				400
			);
		}

		// The extension and the declared type are both the client's word. The
		// content is not.
		if (!str_starts_with((string) $this -> detectedType(), "image/")) {
			throw new UploadedFileError(
				UPLOAD_ERR_EXTENSION,
				"The uploaded file is not an image.",
				400
			);
		}

		return $this -> store($author);
	}

	/**
	 * Move this upload into the file store and record it.
	 *
	 * Every upload gets its own row, even when the content is already held:
	 * the name, the uploader and the lifetime belong to the upload, not to the
	 * bytes.
	 *
	 * @throws	UploadedFileError
	 */
	public function store(?UserBase $author = null): File {
		$this -> check(true);

		// The same guarantee `move_uploaded_file()` gives: a path handed in by
		// the client cannot be passed off as an upload.
		if (empty($this -> temp) || !is_uploaded_file($this -> temp)) {
			throw new UploadedFileError(
				UPLOAD_ERR_NO_FILE,
				"The file was not uploaded through this request.",
				400
			);
		}

		return File::fromPath(
			$this -> temp,
			(string) $this -> name,
			$author ?? (Session::$user instanceof UserBase ? Session::$user : null),
			move: true
		);
	}

	/**
	 * The type to record: the extension's, when blink knows it, otherwise
	 * whatever the content reads as.
	 */
	public function mimetype(): string {
		$known = !empty($this -> extension)
			? mime($this -> extension, charset: "", default: null)
			: null;

		return $known ?: ($this -> detectedType() ?? "application/octet-stream");
	}
}
