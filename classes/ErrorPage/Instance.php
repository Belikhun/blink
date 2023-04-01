<?php
/**
 * Instance.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\ErrorPage;
use Blink\ErrorPage\Exception\ReportNotFound;

class Instance {
	const ERROR_CLIENT = "client";
	const ERROR_SERVER = "server";
	const ERROR_OTHER = "other";

	public String $id;

	public int $status = 200;

	/**
	 * @var ContextGroup[]
	 */
	public Array $contexts = [];

	public $data = null;

	public ?String $php = null;
	public ?String $server = null;
	public ?String $blink = null;

	protected $tipTitle = null;
	protected $tipContent = null;

	public function __construct(String $id) {
		$this -> id = $id;
	}

	protected function hasException() {
		return !empty($this -> data) && !empty($this -> data["exception"]);
	}

	public function info() {
		if ($this -> hasException())
			return [ $this -> data["exception"]["class"], $this -> data["description"] ];

		return $this -> httpInfo();
	}

	public function httpInfo() {
		$statusText = "OK";
		$description = "Everything is good and dandy!";

		switch ($this -> status) {
			case 400:
				$statusText = "HTTP\BadRequest";
				$description = "The request cannot be fulfilled due to bad syntax.";
				break;
			
			case 401:
				$statusText = "HTTP\Unauthorized";
				$description = "Authentication is required and has failed or has not yet been provided.";
				break;
			
			case 403:
				$statusText = "HTTP\Forbidden";
				$description = "Hey, Thats illegal! You are not allowed to access this resource!";
				break;
			
			case 404:
				$statusText = "HTTP\NotFound";
				$description = "Không thể tìm thấy tài nguyên này trên máy chủ.";
				break;
			
			case 405:
				$statusText = "HTTP\MethodNotAllowed";
				$description = "A request method is not supposed for the requested resource.";
				break;
			
			case 406:
				$statusText = "HTTP\NotAcceptable";
				$description = "The requested resource is capable of generating only content not acceptable according to the Accept headers sent in the request.";
				break;
			
			case 408:
				$statusText = "HTTP\RequestTimeout";
				$description = "The client did not produce a request within the time that the server was prepared to wait.";
				break;
			
			case 414:
				$statusText = "HTTP\URITooLong";
				$description = "The URI provided was too long for the server to process.";
				break;
			
			case 429:
				$statusText = "HTTP\TooManyRequest";
				$description = "Hey, you! Yes you. Why you spam here?";
				break;
			
			case 500:
				$statusText = "HTTP\InternalServerError";
				$description = "The server did an oopsie";
				break;
			
			case 502:
				$statusText = "HTTP\BadGateway";
				$description = "The server received an invalid response while trying to carry out the request.";
				break;
			
			default:
				$statusText = "HTTP\SampleText";
				$description = "Much strangery page, Such magically error, wow";
				break;
		}

		if (!empty($this -> data))
			$description = $this -> data["description"];

		return [ $statusText, $description ];
	}

	public function type() {
		if ($this -> status >= 400 && $this -> status < 500)
			return static::ERROR_CLIENT;
		else if ($this -> status >= 500 && $this -> status < 600)
			return static::ERROR_SERVER;

		return static::ERROR_OTHER;
	}

	public function tips() {
		if (!empty($this -> tipTitle))
			return [ $this -> tipTitle, $this -> tipContent ];

		if (!$this -> hasException())
			return [ null, null ];

		$exception = $this -> data["exception"];

		if (!defined("DISABLE_HANDLERS")) {
			try {
				if (!empty($exception["class"]))
					list($this -> tipTitle, $this -> tipContent) = \Handlers::errorPageHint($exception["class"], $exception["data"]);
			} catch (\Throwable $e) {
				define("DISABLE_HANDLERS", true);
				throw $e;
			}
		}

		return [ $this -> tipTitle, $this -> tipContent ];
	}

	public function exception() {
		if (!$this -> hasException())
			return null;

		return $this -> data["exception"];
	}

	/**
	 * Return stack trace frames.
	 * @return \BacktraceFrame[]
	 */
	public function stacktrace() {
		if ($this -> hasException())
			return $this -> data["exception"]["stacktrace"];

		return Array();
	}

	public function sticker() {
		$sticker = "/core/public/stickers/sticker-default.webm";

		switch ($this -> type()) {
			case static::ERROR_SERVER:
				$sticker = "/core/public/stickers/sticker-50x.webm";
				break;

			case static::ERROR_CLIENT:
				$sticker = "/core/public/stickers/sticker-40x.webm";
				break;
		}

		return $sticker;
	}

	/**
	 * Return url to view this report.
	 * @return string
	 */
	public function url() {
		return new \URL("/error/{$this -> id}");
	}

	public function __serialize() {
		return Array(
			"id" => $this -> id,
			"status" => $this -> status,
			"contexts" => $this -> contexts,
			"data" => $this -> data
		);
	}

	public function __unserialize(Array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}

	public static function path(String $id) {
		return \CONFIG::$ERRORS_ROOT . "/{$id}.report";
	}

	public static function create(Array $data = null): Instance {
		$instance = new static(bin2hex(random_bytes(10)));
		$instance -> data = $data;
		$instance -> status = !empty($data)
			? $data["status"]
			: (http_response_code() || 200);

		$instance -> php = phpversion();
		$instance -> server = (!empty($_SERVER["SERVER_SOFTWARE"]))
			? $_SERVER["SERVER_SOFTWARE"]
			: null;
		$instance -> blink = \CONFIG::$BLINK_VERSION;

		$headerContext = new ContextItem("headers", "Headers", getallheaders(), "arrow-right-left");
		$headerContext -> setRenderer([ ContextRenderer::class, "list" ]);

		$request = new ContextGroup("Request");
		$request
			-> add($headerContext);

		$instance -> contexts[] = $request;

		if (!defined("DISABLE_HANDLERS")) {
			try {
				if (!empty($exception["class"])) {
					$instance -> contexts = array_merge(
						$instance -> contexts,
						\Handlers::errorContexts($instance));
				}
			} catch (\Throwable $e) {
				define("DISABLE_HANDLERS", true);
				throw $e;
			}
		}

		file_put_contents(static::path($instance -> id), serialize($instance));
		return $instance;
	}

	public static function get(String $id): Instance {
		$path = static::path($id);

		if (!file_exists($path))
			throw new ReportNotFound($id);

		$content = file_get_contents($path);
		return unserialize($content);
	}
}
