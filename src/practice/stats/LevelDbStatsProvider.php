<?php

declare(strict_types=1);

namespace practice\stats;

use function extension_loaded;
use function strtolower;

/**
 * LevelDB-backed storage.
 *
 * The same embedded key/value store the server already uses for worlds, so it
 * needs no extra service and survives restarts. One key per player, holding the
 * JSON-encoded counters.
 */
final class LevelDbStatsProvider implements StatsProvider{

	private const KEY_PREFIX = "player:";

	private ?\LevelDB $db;

	/**
	 * @throws \RuntimeException if the leveldb extension is missing
	 * @throws \LevelDBException if the database cannot be opened
	 */
	public function __construct(string $path){
		if(!extension_loaded("leveldb")){
			throw new \RuntimeException("The leveldb PHP extension is not available");
		}

		$this->db = new \LevelDB($path);
	}

	public function load(string $playerName) : PlayerStats{
		if($this->db === null){
			return new PlayerStats();
		}

		$raw = $this->db->get(self::key($playerName));

		return $raw === false || $raw === "" ? new PlayerStats() : PlayerStats::fromJson($raw);
	}

	public function save(string $playerName, PlayerStats $stats) : void{
		$this->db?->put(self::key($playerName), $stats->toJson());
	}

	/**
	 * LevelDB::close() is deprecated in php-leveldb; dropping the handle is what
	 * actually releases the database lock, the same way the core does it.
	 */
	public function close() : void{
		$this->db = null;
	}

	private static function key(string $playerName) : string{
		return self::KEY_PREFIX . strtolower($playerName);
	}
}
