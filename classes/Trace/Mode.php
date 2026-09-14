<?php

namespace Blink\Trace;

/**
 * The stored codes of the built-in modes: what kind of action an entry
 * records. Lookup and naming live on {@see ModeRegistry}.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Mode {
	const VIEW = 1;
	const CREATE = 2;
	const UPDATE = 3;
	const DELETE = 4;
	const REPORT = 5;
}
