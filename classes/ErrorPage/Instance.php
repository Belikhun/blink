<?php

namespace Blink\ErrorPage;

use Blink\BacktraceFrame;
use Blink\Environment;
use Blink\ErrorPage\Exception\ReportNotFound;
use Blink\Ether;
use Blink\Ether\ErrorPage\ErrorPageContext;
use Blink\Http\Router;
use Blink\Server;
use Blink\Session;
use Blink\URL;
use function Blink\backtrace;
use function Blink\convertSize;
use function Blink\fileGet;
use function Blink\filePut;
use function Blink\getClientIP;
use function Blink\getHeader;
use function Blink\getRelativePath;
use function Blink\requestHeaders;
use function Blink\renderErrorPage;
use function Blink\stringify;

/**
 * An error page instance.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Instance {
	const ERROR_CLIENT = "client";
	const ERROR_SERVER = "server";
	const ERROR_OTHER = "other";

	/**
	 * Sources of error page advice, registered by the application.
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
	}

	protected function hasException() {
		return !empty($this -> data) && !empty($this -> data["exception"]);
	}

	public function info() {
		if ($this -> hasException() && !empty($this -> data["description"])) {
			return array(
				$this -> data["exception"]["class"],
				$this -> data["description"],
				$this -> data["details"] ?: null
			);
		}

		return $this -> httpInfo();
	}

	public function httpInfo() {
		$statusText = "OK";
		$description = "Everything is good and dandy!";
		$info = null;

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
				$description = "Much strangely page, Such magically error, wow!";
				break;
		}

		if (!empty($this -> data) && !empty($this -> data["description"]))
			$description = $this -> data["description"];

		return [ $statusText, $description, $info ];
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

		/** @var array */
		$exception = $this -> data["exception"];

		if (empty($exception["class"]))
			return [ null, null ];

		list($this -> tipTitle, $this -> tipContent) = static::hint(
			$exception["class"],
			$exception["data"]
		);

		return [ $this -> tipTitle, $this -> tipContent ];
	}

	/**
	 * Advice on what to do about an exception, as `[title, content]`. The
	 * content is rendered as HTML.
	 *
	 * @param	string			$class
	 * @param	?array|?object	$data	The exception's data, as
	 * 									{@see \Blink\Exception\BaseException::$data} carries it.
	 * @return	array
	 */
	protected static function hint(string $class, $data = null): array {
		global $AUTOLOAD_MISSES;

		$title = null;
		$content = "";

		switch ($class) {
			case \Blink\Exception\ClassNotDefined::class: {
				$title = __("errorpage.classNotDefined.title");
				$content = __("errorpage.classNotDefined.content", array(
					"class" => $data["class"],
					"file" => getRelativePath($data["file"])
				));

				$content .= Renderer::button(
					__("errorpage.openFile"),
					link: "vscode://file/" . urlencode($data["file"]),
					icon: "file-pen"
				);
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
					"class" => $data["class"],
					"from" => $data["from"]
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
					"module" => $data["module"]
				));
				break;
			}

			case \Blink\Exception\NotLoggedIn::class: {
				$title = __("errorpage.notLoggedIn.title");
				$content = __("errorpage.notLoggedIn.content");
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
	 * Register a source of error page advice. The callable takes the exception
	 * class and its data, and returns `[title, content]` or nothing.
	 */
	public static function addTipProvider(callable $provider) {
		static::$tipProviders[] = $provider;
	}

	/**
	 * Register a source of extra context groups, shown at the bottom of the
	 * page. The callable takes the instance and returns
	 * {@see ContextGroup}[].
	 */
	public static function addContextProvider(callable $provider) {
		static::$contextProviders[] = $provider;
	}

	public function exception() {
		if (!$this -> hasException())
			return null;

		return $this -> data["exception"];
	}

	/**
	 * Return stack trace frames.
	 * @return BacktraceFrame[]
	 */
	public function stacktrace() {
		if ($this -> hasException())
			return $this -> data["exception"]["stacktrace"];

		return array();
	}

	public function sticker() {
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

	/**
	 * Return url to view this report.
	 * @return URL
	 */
	public function url(): URL {
		return new URL("/error/{$this -> id}");
	}

	public function __serialize() {
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
			"blink" => $this -> blink
		);
	}

	public function __unserialize(array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}

	public static function path(string $id) {
		return \CONFIG::$ERRORS_ROOT . "/{$id}.report";
	}

	public static function create(array $data = null): Instance {
		global $PATH, $AUTOLOADED, $AUTOLOAD_MISSES, $RUNTIME;

		$instance = new static(bin2hex(random_bytes(10)));
		$instance -> data = $data;
		$instance -> path = $PATH;
		$instance -> method = Server::$METHOD ?? "ERRR";
		$instance -> protocol = Server::$PROTOCOL ?? "ERRR";
		$instance -> ip = getClientIP();
		$instance -> status = !empty($data)
			? $data["status"]
			: (http_response_code() || 200);

		$instance -> php = phpversion();
		$instance -> server = Server::$SOFTWARE;
		$instance -> blink = \CONFIG::$BLINK_VERSION;



		$request = new ContextGroup("Request");

		$infoContext = new ContextItem("info", "Info", array(
			"Path" => $instance -> path,
			"Method" => $instance -> method,
			"Status Code" => $instance -> status,
			"Protocol" => $instance -> protocol,
			"IP" => $instance -> ip,
			"Runtime" => $data["runtime"] . "s"
		), "info");
		$infoContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$request -> add($infoContext);

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
			"content" => fileGet("php://input"),
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

		$routes = array_map(function ($item) { return $item -> __toString(); }, Router::getRoutes());
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
		foreach ($AUTOLOADED as $class => $path)
			$autoload["<b>{$class}</b>"] = getRelativePath($path);

		foreach ($AUTOLOAD_MISSES as $class => $tried)
			$autoload["<b>{$class}</b>"] = "[not found] " . implode(", ", array_map("\\Blink\\getRelativePath", $tried));

		$autoloadContext = new ContextItem("autoload", "Autoload", $autoload, "truck-ramp-box");
		$autoloadContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($autoloadContext);

		if (!empty(\Blink\Debug::$output)) {
			$debugContext = new ContextItem("debug", "Debug Output", \Blink\Debug::$output, "bug");
			$debugContext -> setRenderer([ ContextRenderer::class, "string" ]);
			$app -> add($debugContext);
		}
		
		if (!empty($data["data"])) {
			try {
				// We might fail at this step if data contains unserializable stuff.
				// (eg. functions)
				$edata = (array) $data["data"];

				$dataContext = new ContextItem("edata", "Exception Data", $edata, "asterisk");
				$dataContext -> setRenderer([ ContextRenderer::class, "list" ]);
				$app -> add($dataContext);
			} catch (\Throwable $e) {
				// Don't need to do any other action here.
			}
		}

		$appEnvsContext = new ContextItem("environments", "Environments", Environment::$values, "leaf");
		$appEnvsContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$app -> add($appEnvsContext);

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

		$requests = array_map(function ($item) { return $item -> __toString(); }, \Blink\Metric::$requests);
		$requestsContext = new ContextItem("requests", "Requests", $requests, "arrow-up-bucket");
		$requestsContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$metrics -> add($requestsContext);

		$queries = array_map(function ($item) { return $item -> __toString(); }, \Blink\Metric::$queries);
		$queriesContext = new ContextItem("queries", "Queries", $queries, "database");
		$queriesContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$metrics -> add($queriesContext);

		$files = array_map(function ($item) { return $item -> __toString(); }, \Blink\Metric::$files);
		$filesContext = new ContextItem("files", "Files", $files, "file-pen");
		$filesContext -> setRenderer([ ContextRenderer::class, "list" ]);
		$metrics -> add($filesContext);

		$timingContext = new ContextItem("timings", "Timings", array(
			"start" => (!empty(\Blink\Metric::$timings))
				? \Blink\Metric::$timings[0] -> start
				: $RUNTIME -> start,
			"end" => microtime(true),
			"timings" => \Blink\Metric::$timings,
		), "stopwatch");
		$timingContext -> setRenderer([ ContextRenderer::class, "metricTiming" ]);
		$metrics -> add($timingContext);



		$instance -> contexts[] = $request;
		$instance -> contexts[] = $app;
		$instance -> contexts[] = $metrics;

		foreach (static::$contextProviders as $provider) {
			$instance -> contexts = array_merge(
				$instance -> contexts,
				$provider($instance)
			);
		}

		Ether::dispatchErrorPage(
			new ErrorPageContext($instance, $data, \Blink::debuggingEnabled()),
			static function (ErrorPageContext $context) {
				return $context;
			}
		);

		filePut(static::path($instance -> id), serialize($instance));
		return $instance;
	}

	public static function get(string $id): Instance {
		$path = static::path($id);

		if (!file_exists($path))
			throw new ReportNotFound($id);

		$content = fileGet($path);
		return unserialize($content);
	}

	public static function handle(string $id) {
		$instance = static::get($id);
		renderErrorPage($instance);
	}

	public static function index() {
		global $PATH;

		if (empty($_SESSION["LAST_ERROR"])) {
			$instance = new static("invalid");
			$instance -> path = $PATH;
			$instance -> method = Server::$METHOD;
			$instance -> protocol = Server::$PROTOCOL;
			$instance -> ip = getClientIP();
			$instance -> status = 200;
			$instance -> php = phpversion();
			$instance -> server = Server::$SOFTWARE;
			$instance -> blink = \CONFIG::$BLINK_VERSION;
			$instance -> data = [ "exception" => [ "stacktrace" => backtrace() ] ];

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
