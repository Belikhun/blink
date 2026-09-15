<?php

namespace Blink\Query;

use ArrayIterator;
use Iterator;
use Traversable;

/**
 * Walks the records a query returned, turning each one into a model on the way
 * past when the query came from a model.
 *
 * The records are fetched before iteration begins, so this reads better than
 * {@see Query::all()} rather than costing less: nothing here is a cursor into
 * the database. It is still the right thing to loop over when the processing
 * matters more than the fetch, since only one model instance is built at a
 * time rather than one for every row at once.
 *
 * @template	QT
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class QueryIterator implements Iterator {
	protected ?Iterator $iterator = null;

	/**
	 * The model class records are turned into, when there is one.
	 *
	 * @var ?string
	 */
	public ?string $class = null;

	/**
	 * @param	array|Traversable|null	$records
	 */
	public function __construct(mixed $records = null, ?string $class = null) {
		$this -> iterator = static::normalize($records);
		$this -> class = $class;
	}

	/**
	 * Take whatever the query produced and make an iterator of it.
	 *
	 * The drivers hand back a plain array, so that is the usual case, but
	 * anything traversable works and a driver that grows a real cursor will
	 * drop straight in.
	 */
	protected static function normalize(mixed $records): ?Iterator {
		if ($records === null)
			return null;

		if ($records instanceof Iterator)
			return $records;

		if ($records instanceof Traversable)
			return new ArrayIterator(iterator_to_array($records));

		if (is_array($records))
			return new ArrayIterator($records);

		// A count from a write, or something else with no records in it.
		return null;
	}

	/**
	 * @return	QT
	 */
	public function current(): mixed {
		if ($this -> iterator === null)
			return null;

		$record = $this -> iterator -> current();

		return (!empty($this -> class))
			? $this -> class::processRecord($record)
			: $record;
	}

	#[\ReturnTypeWillChange]
	public function key() {
		return $this -> iterator ?-> key();
	}

	public function next(): void {
		$this -> iterator ?-> next();
	}

	public function valid(): bool {
		return $this -> iterator !== null && $this -> iterator -> valid();
	}

	public function rewind(): void {
		$this -> iterator ?-> rewind();
	}

	/**
	 * Release whatever is behind the iterator.
	 *
	 * Calling it is optional, and calling it twice is harmless. A backing
	 * object with nothing to release is the normal case.
	 */
	public function close(): void {
		$iterator = $this -> iterator;
		$this -> iterator = null;

		if (is_object($iterator) && method_exists($iterator, "close"))
			$iterator -> close();
	}

	public function __destruct() {
		$this -> close();
	}
}
