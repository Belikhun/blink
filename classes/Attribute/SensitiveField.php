<?php

namespace Blink\Attribute;

/**
 * Class to mark that this property's value is sensitive and only acessible when specified in getter function.
 * The propery value bearing this will not be included in json encoded data or serialized data by default.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[\Attribute]
class SensitiveField {}
