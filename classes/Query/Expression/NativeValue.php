<?php

namespace Blink\Query\Expression;

use Blink\Query;
use Blink\Query\Interface\Sequelizable;
use function Blink\randString;

/**
 * Navive value
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class NativeValue implements Sequelizable {

	public readonly string $id;

	public readonly mixed $value;

	public bool $trusted = false;

	/**
	 * Construct a new native value to be used in database query.
	 *
	 * | ⚠ DO NOT PASS USER'S INPUT AS TRUSTED NATIVE VALUE! THIS WILL LEAD TO SQL INJECTION!
	 *
	 * @param	mixed	$value		Value to be passed into query.
	 * @param	bool	$trusted	Trust this value. Trusted values will be passed directly into the query.
	 */
	public function __construct(mixed $value, bool $trusted = false) {
		$this -> id = "[f:" . randString(7) . "]";
		$this -> value = $value;
		$this -> trusted = $trusted;
	}

	public function getID(): string {
		return $this -> id;
	}

	public function sequelize(Query $query): array {
		if ($this -> value === null)
			return ["NULL", []];

		if (is_bool($this -> value))
			return [$this -> value ? "TRUE" : "FALSE", []];

		if ($this -> trusted)
			return [$this -> value, []];

		if (is_numeric($this -> value))
			return ["{$this -> value}", []];

		return ["?", [$this -> value]];
	}
}
