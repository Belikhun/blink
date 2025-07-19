<?php

namespace Blink\Query\Expression;

use Blink\Query\Expression\Column;
use Blink\Query\Function\GroupConcatenate;
use Blink\Query\Interface\Sequelizable;
use Blink\Query\Statement\CaseValue;
use Blink\Query\Statement\CaseCondition;

/**
 * Class for quick access to query expressions.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 */
class Expr {
	/**
	 * Create a new case statement that match using conditions.
	 *
	 * @return	CaseCondition
	 * @link	https://dev.mysql.com/doc/refman/9.1/en/case.html
	 */
	public static function case(): CaseCondition {
		return new CaseCondition();
	}

	/**
	 * Create a new case statement that match the supplied column/native value.
	 *
	 * @param	mixed		$value
	 * @return	CaseValue
	 * @link	https://dev.mysql.com/doc/refman/9.1/en/case.html
	 */
	public static function caseValue($value): CaseValue {
		return new CaseValue($value);
	}

	/**
	 * Perform addition of multiple values
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	ArithmeticOperator
	 */
	public static function add(Sequelizable|string|int|float ...$values) {
		return new ArithmeticOperator(ArithmeticOperator::ADD, ...static::processValues($values));
	}

	/**
	 * Perform subtraction of multiple values
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	ArithmeticOperator
	 */
	public static function sub(Sequelizable|string|int|float ...$values) {
		return new ArithmeticOperator(ArithmeticOperator::SUB, ...static::processValues($values));
	}

	/**
	 * Perform multiplication of multiple values
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	ArithmeticOperator
	 */
	public static function mul(Sequelizable|string|int|float ...$values) {
		return new ArithmeticOperator(ArithmeticOperator::MUL, ...static::processValues($values));
	}

	/**
	 * Perform division of multiple values
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	ArithmeticOperator
	 */
	public static function div(Sequelizable|string|int|float ...$values) {
		return new ArithmeticOperator(ArithmeticOperator::DIV, ...static::processValues($values));
	}

	/**
	 * Perform bitwise AND operation of multiple values
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	ArithmeticOperator
	 */
	public static function bitAnd(Sequelizable|string|int|float ...$values) {
		return new ArithmeticOperator(ArithmeticOperator::BIT_AND, ...static::processValues($values));
	}

	/**
	 * Perform bitwise OR operation of multiple values
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	ArithmeticOperator
	 */
	public static function bitOr(Sequelizable|string|int|float ...$values) {
		return new ArithmeticOperator(ArithmeticOperator::BIT_OR, ...static::processValues($values));
	}

	/**
	 * Perform bitwise XOR operation of multiple values
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	ArithmeticOperator
	 */
	public static function bitXor(Sequelizable|string|int|float ...$values) {
		return new ArithmeticOperator(ArithmeticOperator::BIT_XOR, ...static::processValues($values));
	}

	/**
	 * Perform sum of multiple values
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	CallableFunction
	 */
	public static function sum(Sequelizable|string|int|float ...$values) {
		return new CallableFunction("SUM", ...static::processValues($values, resolveAsColumn: true));
	}

	/**
	 * Get the smallest value in the specified values list
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	CallableFunction
	 */
	public static function min(Sequelizable|string|int|float ...$values) {
		return new CallableFunction("MIN", ...static::processValues($values, resolveAsColumn: true));
	}

	/**
	 * Count the number of returning records of a select query.
	 *
	 * @param	Sequelizable|string		$column
	 * @return	CallableFunction
	 */
	public static function count(Sequelizable|string $column) {
		return new CallableFunction("COUNT", static::processValue($column, resolveAsColumn: true));
	}

	/**
	 * Get the largest value in the specified values list
	 *
	 * @param	Sequelizable[]|string[]|int[]|float[]	$values
	 * @return	CallableFunction
	 */
	public static function max(Sequelizable|string|int|float ...$values) {
		return new CallableFunction("MAX", ...static::processValues($values, resolveAsColumn: true));
	}

	/**
	 * Convert strings into lower-case
	 *
	 * @param	Sequelizable[]|string[]					$values
	 * @return	CallableFunction
	 */
	public static function lower(Sequelizable|string|int|float ...$values) {
		return new CallableFunction("LOWER", ...static::processValues($values, resolveAsColumn: true));
	}

	/**
	 * Convert strings into UPPER-CASE
	 *
	 * @param	Sequelizable[]|string[]					$values
	 * @return	CallableFunction
	 */
	public static function upper(Sequelizable|string|int|float ...$values) {
		return new CallableFunction("UPPER", ...static::processValues($values, resolveAsColumn: true));
	}

	/**
	 * Removes leading and trailing spaces from a string
	 *
	 * @param	Sequelizable[]|string[]					$values
	 * @return	CallableFunction
	 */
	public static function trim(Sequelizable|string|int|float ...$values) {
		return new CallableFunction("TRIM", ...static::processValues($values, resolveAsColumn: true));
	}

	/**
	 * Check the value is null
	 *
	 * @param	Column|Sequelizable|string				$value
	 * @return	IsNull
	 */
	public static function isNull(Column|Sequelizable|string $value) {
		return new IsNull(static::processValue($value, resolveAsColumn: true));
	}

	/**
	 * Check the value is not null
	 *
	 * @param	Column|Sequelizable|string				$value
	 * @return	IsNull
	 */
	public static function isNotNull(Column|Sequelizable|string $value) {
		return new IsNull(static::processValue($value, resolveAsColumn: true), flip: true);
	}

	/**
	 * Concatenate with separator.
	 * The first argument is the separator for the rest of the arguments.
	 *
	 * @param	NativeValue|string							$separator		Separator
	 * @param	Sequelizable[]|string[]|int[]|float[]		$values			Concat values
	 * @return	CallableFunction
	 */
	public static function concatWs(NativeValue|string $separator, Sequelizable|string|int|float ...$values) {
		return new CallableFunction(
			"CONCAT_WS",
			static::processValue($separator),
			...static::processValues($values, resolveAsColumn: true)
		);
	}

	/**
	 * Returns a string result with the concatenated non-NULL values from a group.
	 *
	 * https://dev.mysql.com/doc/refman/9.3/en/aggregate-functions.html#function_group-concat
	 *
	 * @param	Column|string		$column
	 * @param	string				$separator
	 * @param	bool				$distinct
	 * @return	GroupConcatenate
	 */
	public static function groupConcat(Column|string $column, string $separator, bool $distinct = false) {
		return new GroupConcatenate(static::processValue($column, resolveAsColumn: true), $separator, $distinct);
	}

	/**
	 * Construct a new native value to be used in database query.
	 *
	 * | ⚠ DO NOT PASS USER'S INPUT AS TRUSTED NATIVE VALUE! THIS WILL LEAD TO SQL INJECTION!
	 *
	 * @param	mixed			$value		Value to be passed into query.
	 * @param	bool			$trusted	Trust this value. Trusted values will be passed directly into the query.
	 * @return	NativeValue
	 */
	public static function value($value, bool $trusted = false): NativeValue {
		return new NativeValue($value, $trusted);
	}

	/**
	 * Construct a new native value to be used in database query.
	 *
	 * | ⚠ DO NOT PASS USER'S INPUT AS TRUSTED NATIVE VALUE! THIS WILL LEAD TO SQL INJECTION!
	 *
	 * @param	mixed			$value		Value to be passed into query.
	 * @param	bool			$trusted	Trust this value. Trusted values will be passed directly into the query.
	 * @return	NativeValue
	 */
	public static function v($value, bool $trusted = false): NativeValue {
		return static::value($value, $trusted);
	}

	/**
	 * Process raw values to sequelizable instances
	 *
	 * @param	mixed			$values
	 * @param	bool			$resolveAsColumn	Prefer to resolve values as a column address
	 * @return	Sequelizable[]
	 */
	public static function processValues(array $values, bool $resolveAsColumn = false) {
		foreach ($values as &$value)
			$value = static::processValue($value, $resolveAsColumn);

		return $values;
	}

	/**
	 * Process a single value into a safe, sequelizable instance
	 *
	 * @param	mixed			$value
	 * @param	bool			$resolveAsColumn	Prefer to resolve the value as a column address
	 * @return	Sequelizable
	 */
	public static function processValue($value, bool $resolveAsColumn = false): Sequelizable {
		if ($value instanceof Sequelizable)
			return $value;

		if (is_array($value))
			return new SequelizableList(static::processValues($value));

		if (is_string($value)) {
			$fallbackNativeValue = false;

			if (preg_match('/^[a-z0-9_]+\.[a-z0-9_]+$/', $value)) {
				$resolveAsColumn = true;
				$fallbackNativeValue = true;
			}

			if ($resolveAsColumn) {
				if (str_contains($value, ".")) {
					[$table, $column] = explode(".", $value);

					$resolvedColumn = Column::instance(
						$table,
						$column,
						fallbackPartial: true
					);

					if ($resolvedColumn instanceof PartialColumn)
						$resolvedColumn -> fallbackNativeValue = $fallbackNativeValue;

					return $resolvedColumn;
				}

				return new PartialColumn($value);
			}
		}

		return static::value($value);
	}
}
