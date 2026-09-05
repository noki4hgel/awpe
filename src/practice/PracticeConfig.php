<?php

declare(strict_types=1);

namespace practice;

use pocketmine\utils\Config;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function max;

/**
 * Typed, dot-path view over config.yml.
 *
 * Everything the core reads from disk goes through here, so a missing or
 * malformed key falls back to a sane default in one place instead of every
 * manager having to guard against it.
 */
final class PracticeConfig{

	public function __construct(private readonly Config $config){
	}

	public function getString(string $path, string $default = "") : string{
		$value = $this->config->getNested($path);

		return is_string($value) ? $value : $default;
	}

	public function getInt(string $path, int $default = 0) : int{
		$value = $this->config->getNested($path);

		return is_int($value) ? $value : $default;
	}

	public function getFloat(string $path, float $default = 0.0) : float{
		$value = $this->config->getNested($path);

		return is_float($value) ? $value : (is_int($value) ? (float) $value : $default);
	}

	public function getBool(string $path, bool $default = false) : bool{
		$value = $this->config->getNested($path);

		return is_bool($value) ? $value : $default;
	}

	/**
	 * @return array<mixed>
	 */
	public function getArray(string $path) : array{
		$value = $this->config->getNested($path);

		return is_array($value) ? $value : [];
	}

	/**
	 * @return string[]
	 */
	public function getStringList(string $path) : array{
		$result = [];
		foreach($this->getArray($path) as $value){
			if(is_string($value)){
				$result[] = $value;
			}
		}

		return $result;
	}

	public function getUpdateInterval() : int{
		return max(1, $this->getInt("update-interval", 20));
	}

	public function getMessage(string $key) : string{
		return $this->getString("messages." . $key);
	}
}
