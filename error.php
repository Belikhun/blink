<?php
/**
 * error.php
 * 
 * Genetic error page. Used when page haven't registered its own
 * error page.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

use Blink\BacktraceFrame;
use Blink\ErrorPage\Instance;
use Blink\ErrorPage\Renderer;
use Blink\HtmlWriter;
use Blink\URL;

/** @var \Blink\ErrorPage\Instance */
$instance = $_SESSION["LAST_ERROR"];

list($statusText, $description, $details) = $instance -> info();
list($tipTitle, $tipContent) = $instance -> tips();
$status = $instance -> status;
$sticker = new URL(getRelativePath(CORE_ROOT . "/public/stickers/" . $instance -> sticker()));
$exception = $instance -> exception();
$basePublic = new URL(getRelativePath(CORE_ROOT . "/public"));
$tipDeco = new URL(getRelativePath(CORE_ROOT . "/public/imgs/tip-deco.svg"));
$statusColor = match ($instance -> type()) {
	Instance::ERROR_CLIENT => "yellow",
	Instance::ERROR_SERVER => "red",
	default => "green"
};

$class = (!empty($exception) && !empty($exception["class"]))
	? $exception["class"]
	: null;

/** @var BacktraceFrame[] */
$stacktrace = $instance -> stacktrace();
$contexts = $instance -> contexts;
http_response_code($status);
header("Access-Control-Allow-Private-Network: true");

?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="<?php echo $basePublic; ?>/default.css">
		<link rel="stylesheet" href="<?php echo $basePublic; ?>/error.css">
		<title><?php echo (!empty($class) ? $class : $statusText) . ": {$description}"; ?></title>
	</head>

	<body>
		<div id="app">
			<header>
				<div class="top">
					<div class="inner">
						<span class="left">
							<a class="link" href="#stacktrace" target="_self" nav-link>
								<?php echo Renderer::icon("stack"); ?>
								Stack
							</a>
	
							<a class="link" href="#context" target="_self" nav-link>
								<?php echo Renderer::icon("context"); ?>
								Context
							</a>
						</span>
	
						<span class="right">
							<a class="link" data-report-link="<?php echo $instance -> url(); ?>">
								<?php echo Renderer::icon("link"); ?>
								<span>Copy report link</span>
							</a>
						</span>
					</div>
				</div>

				<div class="bottom">
					<div class="inner">
						<span class="truncate">
							<?php echo strip_tags($description, [ "code", "b", "i", "u" ]); ?>
						</span>
					</div>
				</div>
			</header>

			<div class="content">
				<div id="details" class="panel">
					<div class="flex flex-row flex-g1 info">
						<?php if (CONFIG::$W_MODE) { ?>
							<video id="sticker" src="<?php echo $sticker -> out(false); ?>" autoplay loop></video>
						<?php } ?>
	
						<span class="flex-g1 block exception">
							<div class="flex flex-row align-center justify-between flex-wrap top">
								<span class="badges flex flex-row align-center flex-wrap">
									<span class="badge status" data-color="<?php echo $statusColor; ?>">
										<?php echo $status; ?>
									</span>
	
									<?php if (!empty($class)) { ?>
										<span class="badge class">
											<?php echo str_replace("\\", "<span>\</span>", $class); ?>
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
											<?php echo Renderer::icon("server"); ?>
											<?php echo $instance -> server; ?>
										</span>
									<?php } ?>
	
									<span>
										<?php echo Renderer::icon("blink"); ?>
										<?php echo $instance -> blink; ?>
									</span>
								</span>
							</div>
	
							<div class="description"><?php echo strip_tags($description, [ "code", "b", "i", "u" ]); ?></div>

							<?php if (!empty($details)) { ?>
								<div class="details"><?php echo $details; ?></div>
							<?php } ?>
						</span>
					</div>

					<?php if (!empty($tipTitle)) { ?>
						<div class="flex flex-row flex-g0 tips active" toggle-target="tip1">
							<div class="block tip" style="background-image: url('<?php echo $tipDeco -> out(true); ?>')">
								<div class="title"><?php echo $tipTitle; ?></div>
								<div class="content"><?php echo $tipContent; ?></div>
							</div>

							<div class="close active" toggle-id="tip1">
								<?php echo Renderer::icon("close"); ?>
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
									$attrs = array(
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

										echo HtmlWriter::startDIV($attrs);
										$badges = array();

										if ($trace -> isVendor()) {
											$badges[] = HtmlWriter::span(
												array( "class" => "badge vendor" ),
												"vendor"
											);
										}

										if ($trace -> isBlink()) {
											$badges[] = HtmlWriter::span(
												array( "class" => "badge blink" ),
												"blink"
											);
										}

										if ($trace -> fault) {
											$badges[] = HtmlWriter::span(
												array( "class" => "badge fault" ),
												"fault"
											);
										}

										if (!empty($badges)) {
											echo HtmlWriter::div(
												array( "class" => "badges" ),
												implode("", $badges)
											);
										}

										echo HtmlWriter::div(
											array( "class" => "path" ),
											$trace -> file . "<code>:{$trace -> line}</code>");
									} else {
										echo HtmlWriter::startDIV($attrs);
									}

									echo HtmlWriter::div(array(
										"class" => "font-semibold"
									), $trace -> getCallString());

									foreach ($trace -> args as $i => $arg) {
										$prefix = "args[{$i}] = ";

										if (is_array($arg)) {
											echo HtmlWriter::code(
												array( "class" => "arg" ),
												"$prefix <b>{$arg[0]}</b> " . htmlspecialchars($arg[1])
											);

											continue;
										}

										echo HtmlWriter::code(
											array( "class" => "arg" ),
											htmlspecialchars($prefix . $arg));
									}

									echo HtmlWriter::endDIV();
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

								$attrs = array(
									"class" => ["flex", "flex-col", "view"],
									"toggle-target" => $trace -> getID()
								);

								if (!$foundfault && $trace -> fault) {
									$attrs["class"][] = "active";
									$foundfault = true;
								}

								echo HtmlWriter::startDIV($attrs);

								echo HtmlWriter::startDIV(array(
									"class" => "header text-sm font-semibold"
								));
								
								echo HtmlWriter::a(
									"vscode://file/" . urlencode($trace -> getFullPath() . ":{$trace -> line}"),
									$trace -> file . "<code>:{$trace -> line}</code>",
									array( "class" => "open-file" )
								);

								echo HtmlWriter::endDIV();

								echo renderSourceCode($trace -> getFullPath(), $trace -> line, 27);

								echo HtmlWriter::endDIV();
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

			<div id="footer" class="content">
				Report ID: <?php echo $instance -> id; ?>

				<span class="separator">//</span>

				<span>
					✨ <a href="<?php echo CONFIG::$BLINK_URL; ?>" target="_blank">blink</a> by Belikhun
				</span>
			</div>
		</div>

		<script src="<?php echo $basePublic; ?>/error.js"></script>
	</body>
</html>
