<?php
/**
 * Exception thrown when error report with specified ID does not exist.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\ErrorPage\Exception;

use Blink\Exception\BaseException;

class ReportNotFound extends BaseException {
	public function __construct(string $id) {
		parent::__construct(
			ERROR_REPORT_NOT_FOUND,
			"Báo cáo lỗi \"{$id}\" không tồn tại trên hệ thống!",
			404, array( "id" => $id ));
	}
}
