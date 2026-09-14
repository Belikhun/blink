<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

/**
 * The schema is behind the layers on disk and could not be brought forward.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DatabaseNotUpgraded extends BaseException {
	public function __construct(int $applied, int $total) {
		parent::__construct(
			DATABASE_NOT_UPGRADED,
			__("terra.notUpgraded", array( "applied" => $applied, "total" => $total )),
			500,
			array( "applied" => $applied, "total" => $total ),
			__("terra.notUpgraded.details")
		);
	}
}
