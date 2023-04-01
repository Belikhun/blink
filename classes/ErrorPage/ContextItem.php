<?php
/**
 * ContextItem.php
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
use HTMLBuilder;

class ContextItem {
	public String $id;
	public String $name;
	public ?String $icon = null;

	/**
	 * Renderer used to render the data.
	 * @var string|array
	 */
	protected $renderer = null;

	/**
	 * @var string|array|object
	 */
	public $data;

	public function __construct(String $id, String $name, $data, String $icon = null) {
		$this -> id = $id;
		$this -> name = $name;
		$this -> data = $data;
		$this -> icon = $icon;
	}

	public function setRenderer(String|Array $callable) {
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
		echo HTMLBuilder::build("a", Array(
			"target" => "_self",
			"href" => "#context-{$this -> id}",
			"class" => "context-nav-item",
			"nav-link" => true,
			"nav-target" => "context-{$this -> id}-target"
		), "", false);

		if (!empty($this -> icon))
			echo ContextRenderer::icon($this -> icon);

		echo HTMLBuilder::span([ "class" => "label" ], $this -> name);
		echo HTMLBuilder::end("a");
	}

	public function render() {
		echo HTMLBuilder::startDIV([ "class" => "context-item", "id" => "context-{$this -> id}-target" ]);
		echo HTMLBuilder::div([ "class" => "scroll-target", "id" => "context-{$this -> id}" ]);

		$title = $this -> name;

		if (!empty($this -> icon))
			$title .= ContextRenderer::icon($this -> icon);

		echo HTMLBuilder::build("h1", [ "class" => "title" ], $title);

		$content = "";

		if (!empty($this -> renderer)) {
			if (is_callable($this -> renderer)) {
				try {
					$content = call_user_func($this -> renderer, $this -> data);
				} catch (\Throwable $e) {
					$content = HTMLBuilder::build("pre", [], get_class($e) . ": " . $e -> getMessage());
				}
			} else {
				$content = HTMLBuilder::build("pre", [], "Renderer [" . $this -> getCallString() . "] is not callable!"); 
			}
		} else {
			$content = HTMLBuilder::build("pre", [], "Renderer not set for this context."); 
		}

		echo $content;
		echo HTMLBuilder::endDIV();
	}

	public function __serialize() {
		return Array(
			"id" => $this -> id,
			"name" => $this -> name,
			"icon" => $this -> icon,
			"renderer" => $this -> renderer,
			"data" => $this -> data
		);
	}

	public function __unserialize(Array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}