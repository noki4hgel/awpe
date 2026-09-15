<?php

declare(strict_types=1);

namespace practice\util;

use function array_key_exists;
use function strtolower;

/**
 * Reading keys that YAML mangles.
 *
 * The parser follows YAML 1.1, where a bare y, n, yes, no, on and off are
 * booleans — so "y: 64" arrives as the key true, which PHP stores as 1. A
 * coordinate written the way anybody would write it silently disappears. This
 * looks the key up under its mangled form as well.
 */
final class ConfigKeys{

	private function __construct(){
		//NOOP
	}

	/**
	 * @param array<mixed> $data
	 */
	public static function get(array $data, string $key) : mixed{
		if(array_key_exists($key, $data)){
			return $data[$key];
		}

		$mangled = match(strtolower($key)){
			"y", "yes", "on", "true" => 1,
			"n", "no", "off", "false" => 0,
			default => null
		};

		return $mangled !== null && array_key_exists($mangled, $data) ? $data[$mangled] : null;
	}
}
