<?php

namespace Blink\Exception;

use Blink\Exception\BaseException;

class JSONDecodeError extends BaseException {
	public function __construct(string $file, string $message, $data) {
		$file = \Blink\getRelativePath($file);
		parent::__construct(INVALID_JSON, "json_decode({$file}): {$message}", 500, array( "file" => $file, "data" => $data ));
	}
}
