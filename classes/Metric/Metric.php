<?php

namespace Blink;

/**
 * Metric.php
 * 
 * Metric base class
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Metric {
	/** @var Metric\Request[] */
	public static $requests = array();

	/** @var Metric\Query[] */
	public static $queries = array();

	/** @var Metric\File[] */
	public static $files = array();

	/** @var Metric\Timing[] */
	public static $timings = array();
}