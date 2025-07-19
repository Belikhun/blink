<?php

namespace Blink\Utils;

/**
 * Class providing paging information.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Paging {
	/**
	 * Total items.
	 *
	 * @var int
	 */
	public int $total;

	/**
	 * Amount of items to show at once.
	 *
	 * @var int
	 */
	public int $show;

	/**
	 * Amount of items will be shown.
	 *
	 * @var int
	 */
	public int $count;

	/**
	 * Current page.
	 *
	 * @var int
	 */
	public int $page;

	/**
	 * Maximum pages can have.
	 *
	 * @var int
	 */
	public int $maxPage;

	public int $from;

	public int $to;

	public function __construct(int $total, int $show = 10) {
		$this -> total = $total;
		$this -> show = $show;
	}

	/**
	 * Return page info for querying.
	 *
	 * @param   int     $page
	 * @return  array
	 */
	public function page(int $page) {
		$this -> maxPage = max(ceil($this -> total / $this -> show), 1);
		$this -> page = min($page, $this -> maxPage);
		$this -> from = $this -> show * ($page - 1);
		$this -> to = min($this -> total, ($this -> show * $page) - 1);
		$this -> count = ($this -> to - $this -> from) + 1;

		return array($this -> from, $this -> count);
	}

	/**
	 * Process array of data and return exactly the items with the
	 * page number specified.
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function process(array $data) {
		if (empty($this -> from))
			$this -> page(1);

		return array_slice($data, $this -> from, $this -> count);
	}

	public function __serialize() {
		return array(
			"total" => $this -> total,
			"show" => $this -> show,
			"page" => $this -> page,
			"maxPage" => $this -> maxPage,
			"from" => $this -> from,
			"to" => $this -> to
		);
	}
}
