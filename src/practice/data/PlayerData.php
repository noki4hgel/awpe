<?php

declare(strict_types=1);

namespace practice\data;

use function array_filter;
use function explode;
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
	public const FIELD_DUELS = "duels";

	public const DUEL_ELO = "elo";
	public const DUEL_WINS = "wins";
	public const DUEL_LOSSES = "losses";

	/**
	 * @param array<string, bool>                                   $settings only values that differ from a setting's default are kept
	 * @param array<string, array{elo: int, wins: int, losses: int}> $duels    per ladder; a ladder never played has no entry
	 */
	public function __construct(
		private string $name = "",
		private int $kills = 0,
		private int $deaths = 0,
		private ?string $rank = null,
		private array $settings = [],
		private array $duels = []
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
	 * Elo on a ladder, or the starting value for a ladder never played.
	 */
	public function getDuelElo(string $ladder, int $startingElo) : int{
		return $this->duels[$ladder][self::DUEL_ELO] ?? $startingElo;
	}

	public function getDuelWins(string $ladder) : int{
		return $this->duels[$ladder][self::DUEL_WINS] ?? 0;
	}

	public function getDuelLosses(string $ladder) : int{
		return $this->duels[$ladder][self::DUEL_LOSSES] ?? 0;
	}

	/**
	 * Records a finished duel. Elo is only touched for ranked matches, which is
	 * why it is passed in rather than always written.
	 */
	public function recordDuel(string $ladder, bool $won, ?int $newElo, int $startingElo) : void{
		$entry = $this->duels[$ladder] ?? [self::DUEL_ELO => $startingElo, self::DUEL_WINS => 0, self::DUEL_LOSSES => 0];
		if($won){
			$entry[self::DUEL_WINS]++;
		}else{
			$entry[self::DUEL_LOSSES]++;
		}
		if($newElo !== null){
			$entry[self::DUEL_ELO] = max(0, $newElo);
		}

		$this->duels[$ladder] = $entry;
	}

	/**
	 * A numeric value by dotted path, e.g. "kills" or "duels.nodebuff.elo" —
	 * how a leaderboard names the field it ranks by.
	 */
	public function getNumber(string $path) : ?int{
		$value = $this->toArray();
		foreach(explode(".", $path) as $part){
			if(!is_array($value) || !isset($value[$part])){
				return null;
			}
			$value = $value[$part];
		}

		return is_int($value) ? $value : null;
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

		$duels = [];
		if(is_array($data[self::FIELD_DUELS] ?? null)){
			foreach($data[self::FIELD_DUELS] as $ladder => $entry){
				if(!is_string($ladder) || !is_array($entry)){
					continue;
				}
				$duels[$ladder] = [
					self::DUEL_ELO => is_int($entry[self::DUEL_ELO] ?? null) ? max(0, $entry[self::DUEL_ELO]) : 0,
					self::DUEL_WINS => is_int($entry[self::DUEL_WINS] ?? null) ? max(0, $entry[self::DUEL_WINS]) : 0,
					self::DUEL_LOSSES => is_int($entry[self::DUEL_LOSSES] ?? null) ? max(0, $entry[self::DUEL_LOSSES]) : 0
				];
			}
		}

		return new self(
			is_string($data[self::FIELD_NAME] ?? null) ? $data[self::FIELD_NAME] : "",
			max(0, is_int($data[self::FIELD_KILLS] ?? null) ? $data[self::FIELD_KILLS] : 0),
			max(0, is_int($data[self::FIELD_DEATHS] ?? null) ? $data[self::FIELD_DEATHS] : 0),
			is_string($data[self::FIELD_RANK] ?? null) ? $data[self::FIELD_RANK] : null,
			$settings,
			$duels
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
			self::FIELD_SETTINGS => $this->settings,
			self::FIELD_DUELS => $this->duels
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
