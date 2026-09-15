<?php

declare(strict_types=1);

namespace practice\data;

use pocketmine\utils\Config;
use function arsort;
use function array_slice;
use function is_array;
use function is_int;
use function is_string;
use function strtolower;

/**
 * Plain JSON file storage, used as a fallback when LevelDB is unavailable.
 */
final class JsonDataProvider implements DataProvider{

	private Config $config;

	public function __construct(string $file){
		$this->config = new Config($file, Config::JSON, []);
	}

	public function load(string $playerName) : PlayerData{
		$raw = $this->config->get(strtolower($playerName), []);
		if(!is_array($raw)){
			return new PlayerData($playerName);
		}

		$data = PlayerData::fromArray($raw);
		if($data->getName() === ""){
			$data->setName($playerName);
		}

		return $data;
	}

	public function save(string $playerName, PlayerData $data) : void{
		$this->config->set(strtolower($playerName), $data->toArray());
		$this->config->save();
	}

	public function getTop(string $field, int $limit) : array{
		if($limit <= 0){
			return [];
		}

		$scores = [];
		foreach($this->config->getAll() as $key => $raw){
			if(!is_array($raw)){
				continue;
			}

			$value = PlayerData::fromArray($raw)->getNumber($field);
			if($value === null || $value <= 0){
				continue;
			}

			$name = is_string($raw[PlayerData::FIELD_NAME] ?? null) ? $raw[PlayerData::FIELD_NAME] : (string) $key;
			$scores[$name] = $value;
		}

		arsort($scores);

		return array_slice($scores, 0, $limit, true);
	}

	public function close() : void{
		$this->config->save();
	}
}
