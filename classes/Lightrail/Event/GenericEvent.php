<?php

namespace Blink\Lightrail\Event;

/**
 * An event with no class of its own.
 *
 * For a one-off signal not worth naming. Anything a handler is meant to match
 * on should get a real class instead, since every generic event looks the same
 * to the registry.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class GenericEvent extends BaseEvent {}
