<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;
use Blink\HtmlWriter;

/**
 * DML exception class.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseException extends BaseException {
	public function __construct(
		string $error,
		string $details = null,
		array|object $data = null,
		string $sql = null,
		array $params = null
	) {
		parent::__construct(SQL_ERROR, $error, 500, $data, details: $details);

		if (!empty($sql)) {
			if (!$this -> details)
				$this -> details = "";

			$this -> details .= HtmlWriter::tag("pre", $sql, array("style" => "margin-top: 1rem;"));

			if (!empty($params))
				$this -> details .= HtmlWriter::tag("pre", \Blink\stringify($params));
		}
	}
}
