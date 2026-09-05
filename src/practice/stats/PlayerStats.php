<?php

declare(strict_types=1);

namespace practice\stats;

use function is_array;
use function is_int;
use function json_decode;
use function json_encode;
use function max;
use const JSON_THROW_ON_ERROR;

/**
 * The counters kept for one player. Providers only have to move this object in
 * and out of storage, which is what keeps the backends interchangeable.
 */
final class PlayerStats{

	public function __construct(
		private int $kills = 0,
		private int $deaths = 0
	){
	}

	public function getKills() : int{
		return $this->kills;
	}

	public function getDeaths() : int{
		return $this->deaths;
	}

	public function addKill() : void{
		$this->kills++;
	}

	public function addDeath() : void{
		$this->deaths++;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data) : self{
		return new self(
			max(0, is_int($data["kills"] ?? null) ? $data["kills"] : 0),
			max(0, is_int($data["deaths"] ?? null) ? $data["deaths"] : 0)
		);
	}

	/**
	 * @return array<string, int>
	 */
	public function toArray() : array{
		return ["kills" => $this->kills, "deaths" => $this->deaths];
	}

	public static function fromJson(string $json) : self{
		try{
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}catch(\JsonException){
			return new self();
		}

		return is_array($data) ? self::fromArray($data) : new self();
	}

	public function toJson() : string{
		return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
	}
}
