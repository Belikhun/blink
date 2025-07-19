<?php

namespace Blink\Query;

use Iterator;

/**
 * Class for iterating through a recordset returned from `Query -> iterator()`.
 *
 *! **⚠ IMPORTANT:** Remember to call `QueryIterator -> close()` after using it.
 *
 * @template	QT
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class QueryIterator implements Iterator {

	protected mixed $iterator = null;

	/**
	 * The target model class name to translate records into.
	 *
	 * @var ?string
	 */
	public ?string $class = null;

	public function __construct(mixed $iterator = null, ?string $class = null) {
		$this -> iterator = $iterator;
		$this -> class = $class;
	}

	/**
	 * Return current record/
	 *
	 * @return	QT
	 */
	public function current(): mixed {
        $record = $this -> iterator -> current();

		return (!empty($this -> class))
			? $this -> class::processRecord($record)
			: $record;
    }

	#[\ReturnTypeWillChange]
	public function key() {
		if (empty($this -> iterator))
			return null;

        return $this -> iterator -> key();
    }

	public function next(): void {
        $this -> iterator -> next();
    }

	public function valid(): bool {
		if (empty($this -> iterator))
			return false;

        return $this -> iterator -> valid();
    }

	public function close(): void {
        $this -> iterator -> close();
    }

	/**
	 * Rewind is not supported!
	 *
	 * @return void
	 */
	public function rewind(): void {
        return;
    }

	public function __destruct() {
        $this -> close();
    }
}
