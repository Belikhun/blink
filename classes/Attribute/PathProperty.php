<?php

namespace Blink\Attribute;

/**
 * Mark a config setting as a directory blink owns.
 *
 * The directory is created when the setting's section is registered, so
 * nothing later has to check whether it exists, and `config:check` reports it
 * when it cannot be written to.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class PathProperty {}
