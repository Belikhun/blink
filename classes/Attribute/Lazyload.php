<?php

namespace Blink\Attribute;

/**
 * Lazyload.php
 * 
 * Class to mark that this property's value can be lazy-loaded.
 * When used, the proterty's value will only be loaded when it's being accessed.
 * 
 * **NOTE: The property bearing this attribute cannot set itself to `private`!**
 * 
 * The property name must be different from the original name. For example:
 * 
 * ```php
 * #[Lazyload("organization")]
 *	protected Organization $__organization;
 * ```
 * 
 * Also to have proper intellisense, you need to also define the property name in phpdoc of the class.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
#[\Attribute]
class Lazyload {
    /**
     * Construct a new Lazyload Attribute.
     * 
     * @param   string  $name   Name of the original property name.
     */
    public function __construct(String $name) {}
}
