<?php

namespace Blink\Event;

/**
 * A model row was removed. Emitted inside the deletion
 * transaction, so a rollback unwinds whatever the handler did with it.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ModelDeleted extends ModelEvent {}
