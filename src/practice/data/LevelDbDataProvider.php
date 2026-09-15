<?php

declare(strict_types=1);

namespace practice\data;

use function arsort;
use function array_slice;
use function extension_loaded;
use function is_int;
use function is_string;
use function str_starts_with;
use function strtolower;

/**
 * LevelDB-backed storage.
 *
 * The same embedded key/value store the server already uses for worlds, so it
 * needs no extra service and survives restarts. One key per player, holding the
 * JSON-encoded record.
 */
final class LevelDbDataProvider implements DataProvider{

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

	public function load(string $playerName) : PlayerData{
		if($this->db === null){
			return new PlayerData($playerName);
		}

		$raw = $this->db->get(self::key($playerName));
		if($raw === false || $raw === ""){
			return new PlayerData($playerName);
		}

		$data = PlayerData::fromJson($raw);
		if($data->getName() === ""){
			$data->setName($playerName);
		}

		return $data;
	}

	public function save(string $playerName, PlayerData $data) : void{
		$this->db?->put(self::key($playerName), $data->toJson());
	}

	/**
	 * Walks every record. Fine for the player counts a practice server sees; if
	 * the database ever grows past that, keep a sorted index alongside instead.
	 */
	public function getTop(string $field, int $limit) : array{
		if($this->db === null || $limit <= 0){
			return [];
		}

		$scores = [];
		$iterator = $this->db->getIterator();
		for($iterator->rewind(); $iterator->valid(); $iterator->next()){
			$key = $iterator->key();
			if(!is_string($key) || !str_starts_with($key, self::KEY_PREFIX)){
				continue;
			}

			$raw = $iterator->current();
			if(!is_string($raw) || $raw === ""){
				continue;
			}

			$data = PlayerData::fromJson($raw);
			$value = $data->getNumber($field);
			if($value === null || $value <= 0){
				continue;
			}

			$name = $data->getName();
			$scores[$name !== "" ? $name : $key] = $value;
		}

		arsort($scores);

		return array_slice($scores, 0, $limit, true);
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
