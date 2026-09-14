<?php

namespace Blink\Event;

/**
 * A model row was written, whether it was new or not. For a handler
 * that does not care which.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ModelSaved extends ModelEvent {}
