<?php
/**
 * TemplateIllegalCall.php
 * 
 * Exception used when calling template functions illegally.
 * (When not in a template context)
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\Exception;

use Blink\Exception\BaseException;

class TemplateIllegalCall extends BaseException {
	public function __construct() {
		parent::__construct(
			TEMPLATE_ILLEGAL_CALL,
			"Function illegally called when not in a template context!",
			500
		);
	}
}
