<?php

namespace Blink\Http\Response;

/**
 * The JSON envelope blink's API routes answer with: a code, a description,
 * the data, and what the request cost.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class APIResponse extends JsonResponse {
	/**
	 * Response code. Mapped in `consts.php`.
	 */
	public int $code = 0;

	/**
	 * What happened, for the developer reading the response.
	 */
	public string $description = "";

	/**
	 * Additional data passed to the client. A throwable here becomes the
	 * exception section of the payload.
	 */
	protected array|object|null $data = array();

	/**
	 * Digest of the data, so a client can tell whether it changed.
	 */
	protected ?string $hash = null;

	/**
	 * The built payload, so it is never computed twice.
	 */
	private ?array $output = null;

	public function __construct(
		int $code = 0,
		string $description = "Success!",
		int $status = 200,
		array|object $data = array(),
		bool|array|string $hash = false
	) {
		$this -> code($code)
			-> description($description)
			-> status($status)
			-> data($data)
			-> hash($hash)
			-> header("Content-Type", "application/json; charset=utf-8");
	}

	public function code(int $code) {
		$this -> code = $code;
		return $this;
	}

	public function description(string $description) {
		$this -> description = str_replace(BASE_PATH, "", $description);
		return $this;
	}

	public function data(array|object $data) {
		$this -> data = $data;
		$this -> output = null;
		return $this;
	}

	public function hash(bool|array|string $hash) {
		if (is_bool($hash)) {
			if ($hash && (is_array($this -> data) || $this -> data instanceof \stdClass))
				$this -> hash = md5(serialize($this -> data));
		} else if (is_string($hash)) {
			$this -> hash = $hash;
		} else {
			$this -> hash = md5(serialize($hash));
		}

		return $this;
	}

	/**
	 * The full payload, exception section included. What actually goes to the
	 * client passes through {@see ResponsePayloadBuilder::redact()} first.
	 */
	public function output(): array {
		if ($this -> output !== null)
			return $this -> output;

		$this -> output = ResponsePayloadBuilder::build(
			$this -> code,
			$this -> description,
			$this -> status,
			$this -> data,
			$this -> hash
		);

		return $this -> output;
	}

	public function process(): string {
		$this -> json(array_merge(ResponsePayloadBuilder::redact($this -> output()), $this -> json));
		return parent::process();
	}
}
