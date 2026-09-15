<?php

declare(strict_types=1);

namespace practice\punishment\store;

use practice\punishment\Punishment;
use function array_reverse;
use function array_values;
use function extension_loaded;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

/**
 * LevelDB-backed punishment storage.
 *
 *   meta:next-id        last id handed out
 *   punishment:<id>     one record, JSON
 *   history:<name>      ids of a player's punishments, oldest first
 *   device:<deviceId>   lowercase names seen on that device
 *
 * The per-player index keeps "is Steve banned" to a handful of reads no matter
 * how many punishments the server has handed out overall.
 */
final class LevelDbPunishmentStore implements PunishmentStore{

	private const KEY_NEXT_ID = "meta:next-id";
	private const PREFIX_PUNISHMENT = "punishment:";
	private const PREFIX_HISTORY = "history:";
	private const PREFIX_DEVICE = "device:";

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

	public function nextId() : int{
		$raw = $this->db?->get(self::KEY_NEXT_ID);
		$id = (is_string($raw) && is_numeric($raw) ? (int) $raw : 0) + 1;

		$this->db?->put(self::KEY_NEXT_ID, (string) $id);

		return $id;
	}

	public function save(Punishment $punishment) : void{
		if($this->db === null){
			return;
		}

		$this->db->put(self::PREFIX_PUNISHMENT . $punishment->getId(), json_encode($punishment->toArray(), JSON_THROW_ON_ERROR));

		$historyKey = self::PREFIX_HISTORY . $punishment->getTarget();
		$ids = $this->readList($historyKey);
		if(!in_array($punishment->getId(), $ids, true)){
			$ids[] = $punishment->getId();
			$this->db->put($historyKey, json_encode($ids, JSON_THROW_ON_ERROR));
		}
	}

	public function get(int $id) : ?Punishment{
		$raw = $this->db?->get(self::PREFIX_PUNISHMENT . $id);
		if(!is_string($raw) || $raw === ""){
			return null;
		}

		$data = self::decode($raw);

		return is_array($data) ? Punishment::fromArray($data) : null;
	}

	public function getHistory(string $playerName) : array{
		$history = [];
		foreach(array_reverse($this->readList(self::PREFIX_HISTORY . Punishment::key($playerName))) as $id){
			if(!is_int($id)){
				continue;
			}
			$punishment = $this->get($id);
			if($punishment !== null){
				$history[] = $punishment;
			}
		}

		return $history;
	}

	public function linkDevice(string $playerName, string $deviceId) : void{
		if($this->db === null || $deviceId === ""){
			return;
		}

		$key = self::PREFIX_DEVICE . $deviceId;
		$names = $this->readList($key);
		$name = Punishment::key($playerName);
		if(!in_array($name, $names, true)){
			$names[] = $name;
			$this->db->put($key, json_encode($names, JSON_THROW_ON_ERROR));
		}
	}

	public function getNamesOnDevice(string $deviceId) : array{
		if($deviceId === ""){
			return [];
		}

		$names = [];
		foreach($this->readList(self::PREFIX_DEVICE . $deviceId) as $name){
			if(is_string($name)){
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Dropping the handle releases the lock; LevelDB::close() is deprecated.
	 */
	public function close() : void{
		$this->db = null;
	}

	/**
	 * @return list<mixed>
	 */
	private function readList(string $key) : array{
		$raw = $this->db?->get($key);
		if(!is_string($raw) || $raw === ""){
			return [];
		}

		$data = self::decode($raw);

		return is_array($data) ? array_values($data) : [];
	}

	private static function decode(string $json) : mixed{
		try{
			return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}catch(\JsonException){
			return null;
		}
	}
}
