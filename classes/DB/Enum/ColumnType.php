<?php

namespace Blink\DB;

/**
 * Class contain database column details.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
enum ColumnType: string {
	case INT = "int";
	case FLOAT = "float";
	case VARCHAR = "varchar";
	case TEXT = "text";
	case DATETIME = "datetime";
	case UNKNOWN = "unknown";
}
