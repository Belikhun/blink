<?php

namespace Blink;

use Throwable;
use Blink\ErrorPage\ContextGroup;
use Blink\ErrorPage\ContextItem;
use Blink\ErrorPage\ContextRenderer;
use Blink\ErrorPage\Exception\ReportNotFound;
use Blink\ErrorPage\Frame;
use Blink\ErrorPage\Inspector;
use Blink\ErrorPage\Renderer;
use Blink\Ether\ErrorPage\ErrorPageContext;
use Blink\Exception\BaseException;
use Blink\Exception\NotLoggedIn;
use Blink\Http\Response\ResponsePayloadBuilder;
use Blink\Http\Router;
use Blink\Metric\ErrorMetric;

/**
 * An error report: what went wrong, where, and the state of the request
 * around it.
 *
 * Built once when the error happens, serialized to `ERRORS_ROOT/{id}.report`
 * and rendered by `error.php`, from the session right away or from the file
 * later through `/error/{id}`. Server errors are also appended to
 * `meta.ndjson`, an index that lists them without unserializing anything.
 *
 * @author		Belikhun
 * @since		2.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ErrorPage {
	const ERROR_CLIENT = "client";
	const ERROR_SERVER = "server";
	const ERROR_OTHER = "other";

	/**
	 * Sources of advice, registered by the application.
	 *
	 * @var callable[]
	 */
	protected static array $tipProviders = array();

	/**
	 * Sources of extra context groups, registered by the application.
	 *
	 * @var callable[]
	 */
	protected static array $contextProviders = array();

	public string $id;

	public int $status = 200;
	public string $path = "/";
	public string $method = "GET";
	public ?string $protocol = null;
	public ?string $ip = null;
	public int $created;

	/** @var ContextGroup[] */
	public array $contexts = [];

	/** @var array|null */
	public $data = null;

	public ?string $php = null;
	public ?string $server = null;
	public ?string $blink = null;

	protected $tipTitle = null;
	protected $tipContent = null;

	public function __construct(string $id) {
		$this -> id = $id;
		$this -> created = time();
	}

	protected function hasException(): bool {
		return !empty($this -> data) && !empty($this -> data["exception"]);
	}

	/**
	 * `[error, description, details]` for the page header.
	 */
	public function info(): array {
		if ($this -> hasException() && !empty($this -> data["description"])) {
			return array(
				$this -> data["exception"]["class"],
				$this -> data["description"],
				$this -> data["details"] ?: null
			);
		}

		return $this -> httpInfo();
	}

	public function httpInfo(): array {
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
				$description = "The requested resource could not be found on this server.";
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
				$description = "Much strangely page, Such magically error, wow!";
				break;
		}

		if (!empty($this -> data) && !empty($this -> data["description"]))
			$description = $this -> data["description"];

		return [ $statusText, $description, null ];
	}

	public function type(): string {
		if ($this -> status >= 400 && $this -> status < 500)
			return static::ERROR_CLIENT;
		else if ($this -> status >= 500 && $this -> status < 600)
			return static::ERROR_SERVER;

		return static::ERROR_OTHER;
	}

	/**
	 * Advice for this error, as `[title, content]`. Both are null when there
	 * is none.
	 */
	public function tips(): array {
		if (!empty($this -> tipTitle))
			return [ $this -> tipTitle, $this -> tipContent ];

		if (!$this -> hasException())
			return [ null, null ];

		$exception = $this -> data["exception"];

		if (empty($exception["class"]))
			return [ null, null ];

		list($this -> tipTitle, $this -> tipContent) = static::hint(
			$exception["class"],
			$exception["data"] ?? null,
			(string) ($this -> data["description"] ?? "")
		);

		return [ $this -> tipTitle, $this -> tipContent ];
	}

	/**
	 * Advice on what to do about an exception, as `[title, content]`. The
	 * content is HTML.
	 *
	 * @param	string			$class
	 * @param	?array|?object	$data			The exception's data, as
	 * 											{@see BaseException::$data} carries it.
	 * @param	string			$description
	 */
	protected static function hint(string $class, $data = null, string $description = ""): array {
		global $AUTOLOAD_MISSES;

		$title = null;
		$content = "";
		$data = (array) $data;

		switch ($class) {
			case \Blink\Exception\ClassNotDefined::class: {
				$title = __("errorpage.classNotDefined.title");
				$content = __("errorpage.classNotDefined.content", array(
					"class" => $data["class"] ?? "?",
					"file" => getRelativePath($data["file"] ?? "")
				));

				if (!empty($data["file"])) {
					$content .= Renderer::button(
						__("errorpage.openFile"),
						link: "vscode://file/" . urlencode($data["file"]),
						icon: "file-pen"
					);
				}
				break;
			}

			case \Blink\Exception\ClassNotFound::class: {
				$tried = $AUTOLOAD_MISSES[$data["class"] ?? ""] ?? array();

				$title = __("errorpage.classNotFound.title");
				$content = __("errorpage.classNotFound.content", array(
					"class" => $data["class"] ?? "?"
				));

				if (!empty($tried)) {
					$content .= "<ul><li><code>"
						. implode("</code></li><li><code>", array_map("\\Blink\\getRelativePath", $tried))
						. "</code></li></ul>";
				}
				break;
			}

			case \Blink\Http\Exception\RouteNotFound::class: {
				$title = __("errorpage.routeNotFound.title");
				$content = __("errorpage.routeNotFound.content");
				break;
			}

			case \Blink\Exception\InvalidDefinition::class: {
				$title = __("errorpage.invalidDefinition.title");
				$content = __("errorpage.invalidDefinition.content", array(
					"class" => $data["class"] ?? "?",
					"from" => $data["from"] ?? "?"
				));

				if (!empty($data["file"])) {
					$content .= Renderer::button(
						__("errorpage.openFile"),
						link: "vscode://file/" . urlencode($data["file"]),
						icon: "file-pen"
					);
				}
				break;
			}

			case \Blink\Exception\ModuleNotFound::class: {
				$title = __("errorpage.moduleNotFound.title");
				$content = __("errorpage.moduleNotFound.content", array(
					"module" => $data["module"] ?? "?"
				));
				break;
			}

			case NotLoggedIn::class: {
				$title = __("errorpage.notLoggedIn.title");
				$content = __("errorpage.notLoggedIn.content");
				break;
			}

			case \Blink\Exception\EtherInvalidMiddleware::class: {
				$title = __("errorpage.etherInvalidMiddleware.title");
				$content = __("errorpage.etherInvalidMiddleware.content", array(
					"reference" => $data["reference"] ?? "?"
				));
				break;
			}

			case \Blink\Essence\Exception\EssenceBindingNotDefined::class: {
				$title = __("errorpage.essenceBindingNotDefined.title");
				$content = __("errorpage.essenceBindingNotDefined.content", array(
					"abstract" => $data["abstract"] ?? "?"
				));
				break;
			}

			case \Blink\Exception\CodingError::class: {
				// The one coding error blink raises on the application's
				// behalf rather than its own: a request needed a user and no
				// module ever said which class that is.
				if (str_contains($description, "userClass()")) {
					$title = __("errorpage.noUserClass.title");
					$content = __("errorpage.noUserClass.content");
				}
				break;
			}
		}

		// Registered last so an application can speak to an exception blink
		// already has a built-in answer for.
		foreach (static::$tipProviders as $provider) {
			$tip = $provider($class, $data);

			if (!empty($tip[0]))
				list($title, $content) = $tip;
		}

		return array( $title, $content );
	}

	/**
	 * Register a source of advice. The callable takes the exception class and
	 * its data, and returns `[title, content]` or nothing.
	 */
	public static function addTipProvider(callable $provider): void {
		static::$tipProviders[] = $provider;
	}

	/**
	 * Register a source of extra context groups, shown at the bottom of the
	 * page. The callable takes the page and returns {@see ContextGroup}[].
	 */
	public static function addContextProvider(callable $provider): void {
		static::$contextProviders[] = $provider;
	}

	public function exception(): ?array {
		if (!$this -> hasException())
			return null;

		return $this -> data["exception"];
	}

	/**
	 * @return Frame[]
	 */
	public function stacktrace(): array {
		if ($this -> hasException())
			return $this -> data["exception"]["stacktrace"] ?? array();

		return array();
	}

	public function sticker(): string {
		$sticker = "sticker-default.webm";

		switch ($this -> type()) {
			case static::ERROR_SERVER:
				$sticker = match ($this -> status) {
					503 => "sticker-503.webm",
					default => "sticker-50x.webm"
				};
				break;

			case static::ERROR_CLIENT:
				$sticker = match ($this -> status) {
					404 => "sticker-404.webm",
					default => "sticker-40x.webm"
				};
				break;
		}

		return $sticker;
	}

	public function url(): URL {
		return new URL("/error/{$this -> id}");
	}

	public function __serialize(): array {
		return array(
			"id" => $this -> id,
			"status" => $this -> status,
			"path" => $this -> path,
			"method" => $this -> method,
			"protocol" => $this -> protocol,
			"ip" => $this -> ip,
			"contexts" => $this -> contexts,
			"data" => $this -> data,
			"php" => $this -> php,
			"server" => $this -> server,
			"blink" => $this -> blink,
			"created" => $this -> created
		);
	}

	public function __unserialize(array $data): void {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}

	/**
	 * Write the report to disk, index it if it is a server error, and record
	 * the error metric.
	 *
	 * A sign-in prompt is not something anyone reviews later, so those are
	 * never stored.
	 */
	public function save(): static {
		if ($this -> hasException() && ($this -> exception()["class"] ?? null) === NotLoggedIn::class)
			return $this;

		list($error, $description, $details) = $this -> info();

		filePut(static::path($this -> id), serialize($this));

		if ($this -> type() === static::ERROR_SERVER) {
			filePush(static::storePath(), json_encode(array(
				"id" => $this -> id,
				"status" => $this -> status,
				"path" => $this -> path,
				"method" => $this -> method,
				"ip" => $this -> ip,
				"error" => $error,
				"description" => strip_tags($description),
				"details" => $details !== null ? strip_tags($details) : null,
				"user" => $this -> data["user"] ?? null,
				"created" => $this -> created
			)) . "\n");
		}

		$source = $this -> hasException()
			? ($this -> exception()["file"] ?? "?") . ":" . ($this -> exception()["line"] ?? 0)
			: $this -> path;

		new ErrorMetric($this -> id, $error, strip_tags($description), $details, $source);

		return $this;
	}

	public static function path(string $id): string {
		return \CONFIG::$ERRORS_ROOT . "/{$id}.report";
	}

	public static function storePath(): string {
		return \CONFIG::$ERRORS_ROOT . "/meta.ndjson";
	}

	/**
	 * The command that was running, as it was typed.
	 */
	protected static function commandLine(): string {
		global $argv;

		return is_array($argv)
			? "blink " . implode(" ", array_slice($argv, 1))
			: "blink";
	}

	/**
	 * A console command has no request behind it, so there is no path to
	 * record. The command line stands in for one, since that is what a reader
	 * of the report will want to know.
	 */
	protected static function requestPath(): string {
		global $PATH;

		if (!empty($PATH))
			return $PATH;

		return BLINK_IS_CLI ? static::commandLine() : "/";
	}

	protected static function build(?array $data = null): static {
		global $AUTOLOADED, $AUTOLOAD_MISSES, $RUNTIME;

		$debugging = \Blink::debuggingEnabled();

		$instance = new static(bin2hex(random_bytes(10)));
		$instance -> data = $data;
		$instance -> path = static::requestPath();
		$instance -> method = Server::$METHOD ?? "ERRR";
		$instance -> protocol = Server::$PROTOCOL ?? "ERRR";
		$instance -> ip = getClientIP();
		$instance -> status = !empty($data)
			? $data["status"]
			: (http_response_code() ?: 200);

		$instance -> php = phpversion();
		$instance -> server = Server::$SOFTWARE ?? null;
		$instance -> blink = \Blink::version();

		$request = new ContextGroup("Request");
		$instance -> contexts[] = $request;

		$infoContext = new ContextItem("info", "Info", array(
			"Path" => $instance -> path,
			"Method" => $instance -> method,
			"Status Code" => $instance -> status,
			"Protocol" => $instance -> protocol,
			"IP" => $instance -> ip,
			"Runtime" => ($data["runtime"] ?? 0) . "s"
		), "info");
		$infoContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$request -> add($infoContext);

		// Everything past the summary is for a developer's eyes. A report is
		// reachable by anyone holding its id, so with debugging off it keeps
		// no headers, no session, no environment and no source of any kind.
		// The exception's class and location stay, so the index and the
		// console can still say what failed.
		if (!$debugging) {
			if (!empty($instance -> data["exception"]))
				$instance -> data["exception"]["stacktrace"] = array();

			static::extendContexts($instance, $data, false);
			return $instance;
		}

		$paramsContext = new ContextItem("params", "Query String", $_GET, "magnify-glass");
		$paramsContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$request -> add($paramsContext);

		$headerContext = new ContextItem("headers", "Headers", requestHeaders(), "arrow-right-left");
		$headerContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$request -> add($headerContext);

		$form = $_POST;
		foreach ($_FILES as $key => $file)
			$form[$key] = sprintf("[file \"%s\" %s %s]", $file["name"], $file["type"], convertSize($file["size"]));

		$bodyContext = new ContextItem("body", "Body", array(
			"form" => $form,
			"content" => BLINK_IS_CLI ? "" : fileGet("php://input"),
			"type" => explode(";", getHeader("Content-Type", TYPE_TEXT, "text/plain"))[0]
		), "scroll");
		$bodyContext -> setRenderer([ ContextRenderer::class, "body" ]);
		$request -> add($bodyContext);

		$app = new ContextGroup("App");

		$routing = array(
			"Active" => "[no active route]",
			"Callback" => "[unknown]",
			"Arguments" => "[]"
		);

		if (!empty(Router::$active)) {
			$routing["Active"] = (string) Router::$active;
			$routing["Callback"] = stringify(Router::$active -> action);
			$routing["Arguments"] = stringify(Router::$active -> args);
		}

		$routingContext = new ContextItem("routing", "Routing", $routing, "route");
		$routingContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($routingContext);

		$routes = array_map(fn ($item) => $item -> __toString(), Router::getRoutes());
		$routesContext = new ContextItem("routes", "Routes", $routes, "road");
		$routesContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($routesContext);

		$sessionContext = new ContextItem("session", "Session", array(
			"Lifetime" => Session::$lifetime,
			"Session ID" => session_id() ?: "[NULL]",
			"Username" => Session::$username,
			"Status" => [ "PHP_SESSION_DISABLED", "PHP_SESSION_NONE", "PHP_SESSION_ACTIVE" ][session_status()]
		), "user-tag");
		$sessionContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($sessionContext);

		$modules = array();
		foreach (\Blink::modules() as $class => $module)
			$modules[$class] = get_class($module);

		if (!empty(\Blink::$failedModule)) {
			$failed = \Blink::$failedModule;
			$modules["<b>Failed</b>"] = "{$failed['class']} -> {$failed['phase']}()";
		}

		$modulesContext = new ContextItem("modules", "Modules", $modules, "cube");
		$modulesContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($modulesContext);

		$autoload = array();
		foreach ($AUTOLOADED ?? array() as $class => $path)
			$autoload["<b>{$class}</b>"] = getRelativePath($path);

		foreach ($AUTOLOAD_MISSES ?? array() as $class => $tried)
			$autoload["<b>{$class}</b>"] = "[not found] " . implode(", ", array_map("\\Blink\\getRelativePath", $tried));

		$autoloadContext = new ContextItem("autoload", "Autoload", $autoload, "truck-ramp-box");
		$autoloadContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($autoloadContext);

		if (!empty(Debug::$output)) {
			$debugContext = new ContextItem("debug", "Debug Output", Debug::$output, "bug");
			$debugContext -> setRenderer([ ContextRenderer::class, "string" ]);
			$app -> add($debugContext);
		}

		if (!empty($data["data"])) {
			try {
				// Fails when the data holds something that cannot be cast,
				// such as a closure. The rest of the report is unaffected.
				$edata = (array) $data["data"];

				$dataContext = new ContextItem("edata", "Exception Data", $edata, "asterisk");
				$dataContext -> setRenderer([ ContextRenderer::class, "list" ]);
				$app -> add($dataContext);
			} catch (Throwable $e) {
				// Nothing to add in its place.
			}
		}

		$appEnvsContext = new ContextItem("environments", "Environments", Environment::masked(), "leaf");
		$appEnvsContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($appEnvsContext);

		$configContext = new ContextItem("config", "Config", Config::display(), "sliders");
		$configContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($configContext);

		$appConstsContext = new ContextItem("consts", "Constants", array(
			"BASE_PATH" => BASE_PATH,
			"APP_ROOT" => APP_ROOT,
			"VENDOR_ROOT" => VENDOR_ROOT,
			"CORE_ROOT" => CORE_ROOT,
			"DATA_ROOT" => DATA_ROOT
		), "feather");
		$appConstsContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($appConstsContext);

		if (!empty($data["output"])) {
			$appOutputContext = new ContextItem("outbuff", "Output Buffer", $data["output"], "buffer");
			$appOutputContext -> setRenderer([ ContextRenderer::class, "html" ]);
			$app -> add($appOutputContext);
		}

		$metrics = new ContextGroup("Metrics");

		$requests = array_map(fn ($item) => $item -> __toString(), Metric::$requests);
		$requestsContext = new ContextItem("requests", "Requests", $requests, "arrow-up-bucket");
		$requestsContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$metrics -> add($requestsContext);

		$queries = array_map(fn ($item) => $item -> __toString(), Metric::$queries);
		$queriesContext = new ContextItem("queries", "Queries", $queries, "database");
		$queriesContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$metrics -> add($queriesContext);

		$files = array_map(fn ($item) => $item -> __toString(), Metric::$files);
		$filesContext = new ContextItem("files", "Files", $files, "file-pen");
		$filesContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$metrics -> add($filesContext);

		$timingContext = new ContextItem("timings", "Timings", array(
			"start" => (!empty(Metric::$timings))
				? Metric::$timings[0] -> start
				: ($RUNTIME ?-> start ?? microtime(true)),
			"end" => microtime(true),
			"timings" => Metric::$timings,
		), "stopwatch");
		$timingContext -> setRenderer([ ContextRenderer::class, "metricTiming" ]);
		$metrics -> add($timingContext);

		$instance -> contexts[] = $app;
		$instance -> contexts[] = $metrics;

		static::extendContexts($instance, $data, true);

		return $instance;
	}

	/**
	 * Let the application add to the report: registered context providers
	 * first, then the Ether error-page middleware.
	 *
	 * This runs while an error is already being handled, so a provider that
	 * fails is logged and skipped rather than allowed to take the report down
	 * with it.
	 */
	protected static function extendContexts(ErrorPage $instance, ?array $data, bool $debugging): void {
		foreach (static::$contextProviders as $provider) {
			try {
				$instance -> contexts = array_merge($instance -> contexts, $provider($instance) ?: array());
			} catch (Throwable $e) {
				error_log("[blink][errorpage] context provider failed: {$e}");
			}
		}

		try {
			Ether::dispatchErrorPage(
				new ErrorPageContext($instance, $data, $debugging),
				static fn (ErrorPageContext $context) => $context
			);
		} catch (Throwable $e) {
			error_log("[blink][errorpage] error-page middleware failed: {$e}");
		}
	}

	/**
	 * Build and store a report from a response payload.
	 */
	public static function create(?array $data = null): static {
		return static::build($data) -> save();
	}

	/**
	 * Build a report from a response payload without storing it.
	 */
	public static function fromPayload(array $payload): static {
		return static::build($payload);
	}

	/**
	 * Build a report for a throwable without storing it.
	 */
	public static function fromException(Throwable $exception, ?int $status = null): static {
		$code = 1000 + $exception -> getCode();
		$description = $exception -> getMessage();
		$httpStatus = $status ?: 500;

		if ($exception instanceof BaseException) {
			$code = $exception -> code;
			$description = $exception -> description;
			$httpStatus = $status ?: $exception -> status;
		}

		return static::fromPayload(ResponsePayloadBuilder::build($code, $description, $httpStatus, $exception));
	}

	/**
	 * Store a report for a throwable that never reached a response, and return
	 * its id.
	 *
	 * A task that fails inside a runner, or an entry that fails inside a tick,
	 * has nobody to show an error page to, but the report is still the only
	 * place the stack trace and context survive.
	 */
	public static function report(Throwable $exception, ?int $status = null): string {
		return static::fromException($exception, $status) -> save() -> id;
	}

	public static function get(string $id): static {
		$path = static::path($id);

		if (!file_exists($path))
			throw new ReportNotFound($id);

		return unserialize(fileGet($path));
	}

	public static function exists(string $id): bool {
		return file_exists(static::path($id));
	}

	public static function getMetaCount(): int {
		$path = static::storePath();

		if (!file_exists($path))
			return 0;

		$handle = fopen($path, "rb");

		if (!$handle)
			return 0;

		$count = 0;

		while (($line = fgets($handle)) !== false) {
			if (!empty(trim($line)))
				$count++;
		}

		fclose($handle);
		return $count;
	}

	/**
	 * Read index lines newest first, walking the file backwards so a page of
	 * recent entries never reads the whole index.
	 *
	 * @return	string[]
	 */
	protected static function readMetaLines(int $offset = 0, int $amount = 20): array {
		$path = static::storePath();

		if (!file_exists($path) || $amount <= 0)
			return array();

		$handle = fopen($path, "rb");

		if (!$handle)
			return array();

		$size = filesize($path);

		if ($size <= 0) {
			fclose($handle);
			return array();
		}

		$position = $size - 1;
		$skipped = 0;
		$buffer = "";
		$lines = array();

		while ($position >= 0) {
			fseek($handle, $position);
			$char = fgetc($handle);

			if ($char === "\n") {
				$line = rtrim(strrev($buffer), "\r");
				$buffer = "";

				if (!empty($line)) {
					if ($skipped < $offset) {
						$skipped++;
					} else {
						$lines[] = $line;

						if (count($lines) >= $amount)
							break;
					}
				}
			} else {
				$buffer .= $char;
			}

			$position--;
		}

		// The first line of the file has no newline in front of it.
		if (count($lines) < $amount && $buffer !== "") {
			$line = rtrim(strrev($buffer), "\r");

			if (!empty($line) && $skipped >= $offset)
				$lines[] = $line;
		}

		fclose($handle);
		return $lines;
	}

	/**
	 * A page of index entries, newest first.
	 *
	 * @return	array[]
	 */
	public static function getMetas(int $amount = 20, int $page = 1): array {
		$page = max($page, 1);
		$amount = max($amount, 1);
		$lines = static::readMetaLines(($page - 1) * $amount, $amount);
		$metas = array();

		foreach ($lines as $line) {
			$meta = safeJSONParsing(trim($line), assoc: true);

			if (!empty($meta))
				$metas[] = $meta;
		}

		return $metas;
	}

	/**
	 * Keep only the most recent stored reports and trim the index to match.
	 *
	 * @return	array{kept: int, deleted: int, rewritten: bool}
	 */
	public static function purgeOldReports(int $keep = 100): array {
		$keep = max(0, $keep);
		$storePath = static::storePath();
		$lines = ($keep > 0)
			? static::readMetaLines(0, $keep)
			: array();
		$keptIDs = array();

		foreach ($lines as $line) {
			$meta = safeJSONParsing(trim($line), assoc: true);

			if (!empty($meta["id"]))
				$keptIDs[$meta["id"]] = true;
		}

		$deleted = 0;

		foreach (glob(\CONFIG::$ERRORS_ROOT . "/*.report") ?: array() as $path) {
			if (isset($keptIDs[basename($path, ".report")]))
				continue;

			if (@unlink($path))
				$deleted++;
		}

		$rewritten = false;

		if (file_exists($storePath) || !empty($lines)) {
			filePut($storePath, !empty($lines)
				? implode("\n", array_reverse($lines)) . "\n"
				: "");

			$rewritten = true;
		}

		return array(
			"kept" => count($keptIDs),
			"deleted" => $deleted,
			"rewritten" => $rewritten
		);
	}

	/**
	 * Drop index entries whose report file is gone, after reports were deleted
	 * by some other rule than recency.
	 *
	 * @return	int		Entries removed.
	 */
	public static function pruneMetas(): int {
		$storePath = static::storePath();

		if (!file_exists($storePath))
			return 0;

		$kept = array();
		$removed = 0;

		foreach (file($storePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array() as $line) {
			$meta = safeJSONParsing(trim($line), assoc: true);

			if (!empty($meta["id"]) && !static::exists($meta["id"])) {
				$removed++;
				continue;
			}

			$kept[] = $line;
		}

		if ($removed > 0)
			filePut($storePath, !empty($kept) ? implode("\n", $kept) . "\n" : "");

		return $removed;
	}

	public static function handle(string $id): void {
		renderErrorPage(static::get($id));
	}

	public static function index(): void {
		if (empty($_SESSION["LAST_ERROR"])) {
			$instance = new static("invalid");
			$instance -> path = static::requestPath();
			$instance -> method = Server::$METHOD ?? "GET";
			$instance -> protocol = Server::$PROTOCOL ?? null;
			$instance -> ip = getClientIP();
			$instance -> status = 200;
			$instance -> php = phpversion();
			$instance -> server = Server::$SOFTWARE ?? null;
			$instance -> blink = \Blink::version();
			$instance -> data = [ "exception" => [ "stacktrace" => Inspector::backtrace() ] ];

			$hello = new ContextGroup("Hello");
			$world = new ContextItem("world", "World", "This is an error page, but currently we don't have any error to report here.\n*fly away*", "info");
			$world -> setRenderer([ ContextRenderer::class, "string" ]);
			$hello -> add($world);
			$instance -> contexts[] = $hello;
		} else {
			$instance = $_SESSION["LAST_ERROR"];
		}

		renderErrorPage($instance);
	}
}
