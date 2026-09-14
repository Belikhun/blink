<?php
/**
 * Shim for hosts that only run files ending in `.php`.
 *
 * The console lives in `blink`, which is extensionless so it can be made
 * executable and run directly. Some managed hosts and task schedulers refuse
 * to run anything else, so this hands off to it.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

require __DIR__ . "/blink";
