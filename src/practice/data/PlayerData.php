<?php

declare(strict_types=1);

namespace practice\data;

use function array_filter;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use const JSON_THROW_ON_ERROR;

/**
 * Everything stored about one player: counters, rank and toggled settings.
 *
 * Kept as a single record so a player is one read and one write, whichever
 * backend is in use.
 */
final class PlayerData{

	public const FIELD_NAME = "name";
	public const FIELD_KILLS = "kills";
	public const FIELD_DEATHS = "deaths";
	public const FIELD_RANK = "rank";
	public const FIELD_SETTINGS = "settings";

	/**
	 * @param array<string, bool> $settings only values that differ from a setting's default are kept
	 */
	public function __construct(
		private string $name = "",
		private int $kills = 0,
		private int $deaths = 0,
		private ?string $rank = null,
		private array $settings = []
	){
	}

	/**
	 * The name as the player last spelled it. Stored because the record key is
	 * lowercased, and the leaderboard wants the real casing.
	 */
	public function getName() : string{
		return $this->name;
	}

	public function setName(string $name) : void{
		$this->name = $name;
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

	public function getRank() : ?string{
		return $this->rank;
	}

	public function setRank(?string $rank) : void{
		$this->rank = $rank;
	}

	/**
	 * @return array<string, bool>
	 */
	public function getSettings() : array{
		return $this->settings;
	}

	public function getSetting(string $id, bool $default) : bool{
		return $this->settings[$id] ?? $default;
	}

	public function setSetting(string $id, bool $value) : void{
		$this->settings[$id] = $value;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data) : self{
		$settings = [];
		if(is_array($data[self::FIELD_SETTINGS] ?? null)){
			foreach($data[self::FIELD_SETTINGS] as $id => $value){
				if(is_string($id) && is_bool($value)){
					$settings[$id] = $value;
				}
			}
		}

		return new self(
			is_string($data[self::FIELD_NAME] ?? null) ? $data[self::FIELD_NAME] : "",
			max(0, is_int($data[self::FIELD_KILLS] ?? null) ? $data[self::FIELD_KILLS] : 0),
			max(0, is_int($data[self::FIELD_DEATHS] ?? null) ? $data[self::FIELD_DEATHS] : 0),
			is_string($data[self::FIELD_RANK] ?? null) ? $data[self::FIELD_RANK] : null,
			$settings
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray() : array{
		return array_filter([
			self::FIELD_NAME => $this->name,
			self::FIELD_KILLS => $this->kills,
			self::FIELD_DEATHS => $this->deaths,
			self::FIELD_RANK => $this->rank,
			self::FIELD_SETTINGS => $this->settings
		], static fn($value) => $value !== null);
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
