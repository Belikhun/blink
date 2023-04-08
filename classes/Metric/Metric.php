<?php
/**
 * Metric.php
 * 
 * Metric base class
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;

class Metric {
	/** @var Metric\Request[] */
	public static $requests = Array();

	/** @var Metric\Query[] */
	public static $queries = Array();

	/** @var Metric\File[] */
	public static $files = Array();
}