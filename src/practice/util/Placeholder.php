<?php

declare(strict_types=1);

namespace practice\util;

use function str_replace;

/**
 * Tiny helper used everywhere a configurable text may contain {placeholders}.
 * Keeping it in one place means new placeholders only have to be added to the
 * array a manager builds, never to the formatting code itself.
 */
final class Placeholder{

	private function __construct(){
		//NOOP
	}

	/**
	 * @param array<string, string|int|float> $values
	 */
	public static function apply(string $text, array $values) : string{
		$search = [];
		$replace = [];
		foreach($values as $key => $value){
			$search[] = "{" . $key . "}";
			$replace[] = (string) $value;
		}

		return str_replace($search, $replace, $text);
	}

	/**
	 * @param string[]                        $lines
	 * @param array<string, string|int|float> $values
	 *
	 * @return string[]
	 */
	public static function applyAll(array $lines, array $values) : array{
		$result = [];
		foreach($lines as $key => $line){
			$result[$key] = self::apply($line, $values);
		}

		return $result;
	}
}
