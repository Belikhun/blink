<?php

namespace Blink\Terra\Exception;

use Blink\Exception\BaseException;

/**
 * A layer threw while being raised or dropped.
 *
 * Wraps the engine's error with the layer, the instance and the direction,
 * which is what a deploy log needs to say which step to look at. The ledger
 * does not record the layer, so a fixed layer runs again on the next apply.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class LayerFailed extends BaseException {
	public function __construct(string $layer, string $database, string $step, \Throwable $previous) {
		parent::__construct(
			TERRA_LAYER_FAILED,
			"Layer <code>{$layer}</code> failed to {$step} on database instance <code>{$database}</code>: " . $previous -> getMessage(),
			500,
			array( "layer" => $layer, "database" => $database, "step" => $step ),
			get_class($previous)
		);

		$this -> file = $previous -> getFile();
		$this -> line = $previous -> getLine();
	}
}
