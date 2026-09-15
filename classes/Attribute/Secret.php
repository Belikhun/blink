<?php

namespace Blink\Attribute;

/**
 * Mark a config setting as something that must not be displayed.
 *
 * A secret keeps working exactly as it did, and its value is replaced with a
 * mask everywhere blink shows configuration: `config:list`, the error page,
 * and anything else that reads a setting for display rather than for use.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Secret {}
