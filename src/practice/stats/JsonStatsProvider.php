<?php

declare(strict_types=1);

namespace practice\stats;

use pocketmine\utils\Config;
use function is_array;
use function strtolower;

/**
 * Plain JSON file storage, used as a fallback when LevelDB is unavailable.
 */
final class JsonStatsProvider implements StatsProvider{

	private Config $config;

	public function __construct(string $file){
		$this->config = new Config($file, Config::JSON, []);
	}

	public function load(string $playerName) : PlayerStats{
		$data = $this->config->get(strtolower($playerName), []);

		return is_array($data) ? PlayerStats::fromArray($data) : new PlayerStats();
	}

	public function save(string $playerName, PlayerStats $stats) : void{
		$this->config->set(strtolower($playerName), $stats->toArray());
		$this->config->save();
	}

	public function close() : void{
		$this->config->save();
	}
}
