<?php

namespace Blink\ErrorPage;

use Blink\HtmlWriter;

/**
 * ContextItem.php
 * 
 * Represent an error context item in error page.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ContextItem {
	public string $id;
	public string $name;
	public ?string $icon = null;

	/**
	 * Renderer used to render the data.
	 * @var string|array
	 */
	protected $renderer = null;

	/**
	 * @var string|array|object
	 */
	public $data;

	public function __construct(string $id, string $name, $data, string $icon = null) {
		$this -> id = $id;
		$this -> name = $name;
		$this -> data = $data;
		$this -> icon = $icon;
	}

	public function setRenderer(string|array $callable) {
		$this -> renderer = $callable;
		return $this;
	}

	protected function getCallString() {
		if (empty($this -> renderer))
			return "no renderer";

		return is_array($this -> renderer)
			? implode("::", $this -> renderer)
			: $this -> renderer;
	}

	public function renderNavigation() {
		echo HtmlWriter::build("a", array(
			"target" => "_self",
			"href" => "#context-{$this -> id}",
			"class" => "context-nav-item",
			"nav-link" => true,
			"nav-target" => "context-{$this -> id}-target"
		), "", false);

		if (!empty($this -> icon))
			echo Renderer::icon($this -> icon);

		echo HtmlWriter::span([ "class" => "label" ], $this -> name);
		echo HtmlWriter::end("a");
	}

	public function render() {
		echo HtmlWriter::startDIV([ "class" => "context-item", "id" => "context-{$this -> id}-target" ]);
		echo HtmlWriter::div([ "class" => "scroll-target", "id" => "context-{$this -> id}" ]);

		$title = $this -> name;

		if (!empty($this -> icon))
			$title .= Renderer::icon($this -> icon);

		echo HtmlWriter::build("h1", [ "class" => "title" ], $title);

		$content = "";

		if (!empty($this -> renderer)) {
			if (is_callable($this -> renderer)) {
				try {
					$content = call_user_func($this -> renderer, $this -> data);
				} catch (\Throwable $e) {
					$content = HtmlWriter::build("pre", [], get_class($e) . ": " . $e -> getMessage());
				}
			} else {
				$content = HtmlWriter::build("pre", [], "Renderer [" . $this -> getCallString() . "] is not callable!"); 
			}
		} else {
			$content = HtmlWriter::build("pre", [], "Renderer not set for this context."); 
		}

		echo $content;
		echo HtmlWriter::endDIV();
	}

	public function __serialize() {
		return array(
			"id" => $this -> id,
			"name" => $this -> name,
			"icon" => $this -> icon,
			"renderer" => $this -> renderer,
			"data" => $this -> data
		);
	}

	public function __unserialize(array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}
