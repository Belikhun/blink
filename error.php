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

use Blink\ErrorPage\ContextRenderer;
use Blink\ErrorPage\Instance;

/**
 * Current error instance.
 * @var \Blink\ErrorPage\Instance
 */
global $instance;

/** @var \Blink\ErrorPage\Instance */
$instance = $_SESSION["LAST_ERROR"];

list($statusText, $description) = $instance -> info();
list($tipTitle, $tipContent) = $instance -> tips();
$status = $instance -> status;
$sticker = $instance -> sticker();
$exception = $instance -> exception();
$statusColor = match ($instance -> type()) {
	Instance::ERROR_CLIENT => "yellow",
	Instance::ERROR_SERVER => "red",
	default => "green"
};

/** @var BacktraceFrame[] */
$stacktrace = $instance -> stacktrace();
$contexts = $instance -> contexts;
http_response_code($status);
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
				<div class="top">
					<div class="inner">
						<span class="left">
							<a class="link" href="#stacktrace" target="_self" nav-link>
								<?php echo ContextRenderer::icon("stack"); ?>
								Stack
							</a>
	
							<a class="link" href="#context" target="_self" nav-link>
								<?php echo ContextRenderer::icon("context"); ?>
								Context
							</a>
						</span>
	
						<span class="right">
							
						</span>
					</div>
				</div>

				<div class="bottom">
					<div class="inner">
						<span class="truncate">
							<?php echo $description; ?>
						</span>
					</div>
				</div>
			</header>

			<div class="content">
				<div id="details" class="panel">
					<div class="flex flex-row flex-g1 info">
						<video id="sticker" src="<?php echo $sticker; ?>" autoplay loop></video>
	
						<span class="flex-g1 block exception">
							<div class="flex flex-row align-center justify-between flex-wrap top">
								<span class="badges flex flex-row align-center flex-wrap">
									<span class="badge status" data-color="<?php echo $statusColor; ?>">
										<?php echo $status; ?>
									</span>
	
									<?php if (!empty($exception)) { ?>
										<span class="badge class">
											<?php echo str_replace("\\", "<span>\</span>", $exception["class"]); ?>
										</span>
									<?php } ?>
								</span>
	
								<span class="versions flex flex-row align-center">
									<span>
										<span class="wider">PHP</span>
										<?php echo $instance -> php; ?>
									</span>
	
									<?php if (!empty($instance -> server)) { ?>
										<span>
											<?php echo ContextRenderer::icon("server"); ?>
											<?php echo $instance -> server; ?>
										</span>
									<?php } ?>
	
									<span>
										<?php echo ContextRenderer::icon("blink"); ?>
										<?php echo $instance -> blink; ?>
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
								<?php echo ContextRenderer::icon("close"); ?>
							</div>
						</div>
					<?php } ?>
				</div>
			</div>

			<?php if (!empty($stacktrace)) { ?>
				<div id="stacktrace" class="content">
					<div class="flex flex-row panel">
						<span class="flex flex-col flex-g0 left">
							<div class="header">
								<b>Frames</b>
							</div>
							
							<div class="frames">
								<?php
								$foundfault = false;

								foreach ($stacktrace as $i => $trace) {
									$attrs = Array(
										"class" => ["frame", "flex", "flex-col", "align-start", "text-sm"]
									);

									if (!empty($trace -> file)) {
										$attrs["toggle-id"] = $trace -> getID();
										$attrs["toggle-name"] = "stacktrace";

										if (!$foundfault && $trace -> fault) {
											$attrs["toggle-default"] = true;
											$attrs["class"][] = "active";
											$foundfault = true;
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
								?>
							
								<div class="space"></div>
							</div>
						</span>

						<span class="flex flex-col flex-g1 viewer">
							<?php
							$foundfault = false;

							foreach ($stacktrace as $trace) {
								if (empty($trace -> file))
									continue;

								$attrs = Array(
									"class" => ["flex", "flex-col", "view"],
									"toggle-target" => $trace -> getID()
								);

								if (!$foundfault && $trace -> fault) {
									$attrs["class"][] = "active";
									$foundfault = true;
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
				</div>
			<?php } ?>

			<div id="context" class="content">
				<div class="nav">
					<?php foreach ($contexts as $group)
						$group -> renderNavigation(); ?>
				</div>

				<div class="panel">
					<?php foreach ($contexts as $group)
						$group -> render(); ?>
				</div>
			</div>
		</div>

		<script src="/core/public/error.js"></script>
	</body>
</html>
