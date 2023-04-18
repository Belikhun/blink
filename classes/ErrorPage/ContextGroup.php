<?php
/**
 * ContextGroup.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink\ErrorPage;
use HtmlWriter;

class ContextGroup {
	public String $name;

	/**
	 * Colletion of context item in this group.
	 * @var ContextItem[]
	 */
	public Array $items = [];

	public function __construct(String $name) {
		$this -> name = $name;
	}

	public function add(ContextItem $item): ContextGroup {
		$this -> items[] = $item;
		return $this;
	}

	public function renderNavigation() {
		?>
		<div class="context-nav-group">
			<div class="label"><?php echo $this -> name; ?></div>

			<div class="items">
				<?php foreach ($this -> items as $item)
					$item -> renderNavigation(); ?>
			</div>
		</div>
		<?php
	}

	public function render() {
		echo HtmlWriter::startDIV([ "class" => "context-group" ]);
		echo HtmlWriter::div([ "class" => "label" ], $this -> name);

		foreach ($this -> items as $item)
			$item -> render();

		echo HtmlWriter::endDIV();
	}

	public function __serialize() {
		return Array(
			"name" => $this -> name,
			"items" => $this -> items
		);
	}

	public function __unserialize(Array $data) {
		foreach ($data as $key => $value)
			$this -> {$key} = $value;
	}
}
