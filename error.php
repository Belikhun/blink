<?php
/**
 * error.php
 * 
 * Genetic error page. Used when page haven't registered its own
 * error page.
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

$data = null;
$status = 200;
$statusText = "OK";
$description = "Everything is good and dandy!";

if (isset($_SERVER["REDIRECT_STATUS"]))
	$status = $_SERVER["REDIRECT_STATUS"];
elseif (isset($_GET["status"]))
	$status = trim($_GET["status"]);

if (!empty($_SESSION["LAST_ERROR"])) {
	$data = $_SESSION["LAST_ERROR"];
	$status = $data["status"];
}

switch ($status) {
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

$statusColor = "green";
$sticker = "/core/public/stickers/sticker-default.webm";
$tipTitle = "sample tip";
$tipContent = "maybe it will help";

if ($status >= 400 && $status < 500) {
	$statusColor = "yellow";
	$sticker = "/core/public/stickers/sticker-40x.webm";
} else if ($status >= 500 && $status < 600) {
	$statusColor = "red";
	$sticker = "/core/public/stickers/sticker-50x.webm";
}

$exception = null;

/** @var BacktraceFrame[] */
$stacktrace = Array();

if (!empty($data)) {
	$description = $data["description"];

	if (!empty($data["exception"])) {
		$exception = $data["exception"];
		$stacktrace = $exception["stacktrace"];
	}
}

http_response_code($status);

function icon($name) {
	switch ($name) {
		case "server":
			echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M64 80c-8.8 0-16 7.2-16 16V258c5.1-1.3 10.5-2 16-2H448c5.5 0 10.9 .7 16 2V96c0-8.8-7.2-16-16-16H64zM48 320v96c0 8.8 7.2 16 16 16H448c8.8 0 16-7.2 16-16V320c0-8.8-7.2-16-16-16H64c-8.8 0-16 7.2-16 16zM0 320V96C0 60.7 28.7 32 64 32H448c35.3 0 64 28.7 64 64V320v96c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V320zm280 48a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zm120-24a24 24 0 1 1 0 48 24 24 0 1 1 0-48z"/></svg>';
			break;

		case "blink":
			echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M160 256C160 202.1 202.1 160 256 160C309 160 352 202.1 352 256C352 309 309 352 256 352C202.1 352 160 309 160 256zM512 256C512 397.4 397.4 512 256 512C114.6 512 0 397.4 0 256C0 114.6 114.6 0 256 0C397.4 0 512 114.6 512 256zM256 48C141.1 48 48 141.1 48 256C48 370.9 141.1 464 256 464C370.9 464 464 370.9 464 256C464 141.1 370.9 48 256 48z"/></svg>';
			break;

		case "close":
			echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M310.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L160 210.7 54.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L114.7 256 9.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L160 301.3 265.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L205.3 256 310.6 150.6z"/></svg>';
			break;
	}
}

?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="/core/public/default.css">
		<link rel="stylesheet" href="/core/public/error.css">
		<title><?php echo (!empty($exception) ? $exception["class"] : $statusText) . ": {$description}"; ?></title>
	</head>

	<body>
		<div id="app">
			<header>

			</header>

			<div class="content">
				<div id="details" class="panel">
					<div class="flex flex-row flex-g1 info">
						<video id="sticker" src="<?php echo $sticker; ?>" autoplay loop></video>
	
						<span class="flex-g1 block exception">
							<div class="flex flex-row align-center justify-between flex-wrap top">
								<span class="badges flex flex-row align-center flex-wrap">
									<span class="badge status" data-color="<?php echo $statusColor; ?>">
										<?php echo $_SERVER["SERVER_PROTOCOL"]; ?>
										<b><?php echo $status; ?></b>
									</span>
	
									<?php if (!empty($exception)) { ?>
										<span class="badge class"><?php echo $exception["class"]; ?></span>
									<?php } ?>
								</span>
	
								<span class="versions flex flex-row align-center">
									<span>
										<span class="wider">PHP</span>
										<?php echo phpversion(); ?>
									</span>
	
									<span>
										<?php echo icon("server"); ?>
										<?php echo $_SERVER["SERVER_SOFTWARE"]; ?>
									</span>
	
									<span>
										<?php echo icon("blink"); ?>
										<?php echo CONFIG::$BLINK_VERSION; ?>
									</span>
								</span>
							</div>
	
							<div class="description"><?php echo $description; ?></div>
						</span>
					</div>

					<?php if (!empty($tipTitle)) { ?>
						<div class="flex flex-row flex-g0 tips active" toggle-target="tip1">
							<div class="block tip">
								<div class="title"><?php echo $tipTitle; ?></div>
								<div class="content"><?php echo $tipContent; ?></div>
							</div>

							<div class="close active" toggle-id="tip1">
								<?php echo icon("close"); ?>
							</div>
						</div>
					<?php } ?>
				</div>

				<?php if (!empty($stacktrace)) { ?>
					<div id="stacktrace" class="flex flex-row panel">
						<span class="flex flex-col flex-g0 left">
							<div class="header">
								<b>Frames</b>
							</div>
							
							<div class="frames">
								<?php
								$firstframe = false;

								foreach ($stacktrace as $i => $trace) {
									$attrs = Array(
										"class" => ["frame", "flex", "flex-col", "align-start", "text-sm"]
									);

									if (!empty($trace -> file)) {
										$attrs["toggle-id"] = $trace -> getID();
										$attrs["toggle-name"] = "stacktrace";

										if (!$firstframe) {
											$attrs["toggle-default"] = true;
											$attrs["class"][] = "active";
											$firstframe = true;
										}

										echo HTMLBuilder::startDIV($attrs);
										$badges = Array();

										if ($trace -> isVendor()) {
											$badges[] = HTMLBuilder::span(
												Array( "class" => "badge vendor" ),
												"vendor"
											);
										}

										if ($trace -> fault) {
											$badges[] = HTMLBuilder::span(
												Array( "class" => "badge fault" ),
												"fault"
											);
										}

										if (!empty($badges)) {
											echo HTMLBuilder::div(
												Array( "class" => "badges" ),
												implode("", $badges)
											);
										}

										echo HTMLBuilder::div(
											Array( "class" => "path" ),
											$trace -> file . "<code>:{$trace -> line}</code>");
									} else {
										echo HTMLBuilder::startDIV($attrs);
									}

									echo HTMLBuilder::div(Array(
										"class" => "font-semibold"
									), $trace -> getCallString());

									foreach ($trace -> args as $i => $arg) {
										$prefix = "args[{$i}] = ";

										if (is_array($arg)) {
											echo HTMLBuilder::code(
												Array( "class" => "arg" ),
												"$prefix <b>{$arg[0]}</b> " . htmlspecialchars($arg[1])
											);

											continue;
										}

										echo HTMLBuilder::code(
											Array( "class" => "arg" ),
											htmlspecialchars($prefix . $arg));
									}

									echo HTMLBuilder::endDIV();
								}
							?></div>
						</span>

						<span class="flex flex-col flex-g1 viewer">
							<?php
							$firstframe = false;

							foreach ($stacktrace as $trace) {
								if (empty($trace -> file))
									continue;

								$attrs = Array(
									"class" => ["flex", "flex-col", "view"],
									"toggle-target" => $trace -> getID()
								);

								if (!$firstframe) {
									$attrs["class"][] = "active";
									$firstframe = true;
								}

								echo HTMLBuilder::startDIV($attrs);

								echo HTMLBuilder::startDIV(Array(
									"class" => "header text-sm font-semibold"
								));
								
								echo HTMLBuilder::a(
									"vscode://file/" . urlencode($trace -> getFullPath() . ":{$trace -> line}"),
									$trace -> file . "<code>:{$trace -> line}</code>",
									Array( "class" => "open-file" )
								);

								echo HTMLBuilder::endDIV();

								echo renderSourceCode($trace -> getFullPath(), $trace -> line, 27);

								echo HTMLBuilder::endDIV();
							}
						?></span>
					</div>
				<?php } ?>
			</div>

			<pre><?php var_dump($data); ?></pre>
		</div>

		<script src="/core/public/error.js"></script>
	</body>
</html>
