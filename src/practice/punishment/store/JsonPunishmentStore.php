<?php

declare(strict_types=1);

namespace practice\punishment\store;

use pocketmine\utils\Config;
use practice\punishment\Punishment;
use function array_reverse;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Plain JSON file storage, used as a fallback when LevelDB is unavailable.
 *
 * Same shape as the LevelDB store, kept in one file and written on every
 * change — slower, but a punishment must never be lost to a crash.
 */
final class JsonPunishmentStore implements PunishmentStore{

	private const KEY_NEXT_ID = "next-id";
	private const KEY_PUNISHMENTS = "punishments";
	private const KEY_HISTORY = "history";
	private const KEY_DEVICES = "devices";

	private Config $config;

	public function __construct(string $file){
		$this->config = new Config($file, Config::JSON, [
			self::KEY_NEXT_ID => 0,
			self::KEY_PUNISHMENTS => [],
			self::KEY_HISTORY => [],
			self::KEY_DEVICES => []
		]);
	}

	public function nextId() : int{
		$current = $this->config->get(self::KEY_NEXT_ID, 0);
		$id = (is_int($current) ? $current : 0) + 1;

		$this->config->set(self::KEY_NEXT_ID, $id);
		$this->config->save();

		return $id;
	}

	public function save(Punishment $punishment) : void{
		$punishments = $this->section(self::KEY_PUNISHMENTS);
		$punishments[(string) $punishment->getId()] = $punishment->toArray();
		$this->config->set(self::KEY_PUNISHMENTS, $punishments);

		$history = $this->section(self::KEY_HISTORY);
		$ids = is_array($history[$punishment->getTarget()] ?? null) ? $history[$punishment->getTarget()] : [];
		if(!in_array($punishment->getId(), $ids, true)){
			$ids[] = $punishment->getId();
			$history[$punishment->getTarget()] = $ids;
			$this->config->set(self::KEY_HISTORY, $history);
		}

		$this->config->save();
	}

	public function get(int $id) : ?Punishment{
		$data = $this->section(self::KEY_PUNISHMENTS)[(string) $id] ?? null;

		return is_array($data) ? Punishment::fromArray($data) : null;
	}

	public function getHistory(string $playerName) : array{
		$ids = $this->section(self::KEY_HISTORY)[Punishment::key($playerName)] ?? [];
		if(!is_array($ids)){
			return [];
		}

		$history = [];
		foreach(array_reverse($ids) as $id){
			if(is_int($id) && ($punishment = $this->get($id)) !== null){
				$history[] = $punishment;
			}
		}

		return $history;
	}

	public function linkDevice(string $playerName, string $deviceId) : void{
		if($deviceId === ""){
			return;
		}

		$devices = $this->section(self::KEY_DEVICES);
		$names = is_array($devices[$deviceId] ?? null) ? $devices[$deviceId] : [];
		$name = Punishment::key($playerName);
		if(in_array($name, $names, true)){
			return;
		}

		$names[] = $name;
		$devices[$deviceId] = $names;
		$this->config->set(self::KEY_DEVICES, $devices);
		$this->config->save();
	}

	public function getNamesOnDevice(string $deviceId) : array{
		$names = $this->section(self::KEY_DEVICES)[$deviceId] ?? [];
		if(!is_array($names)){
			return [];
		}

		$result = [];
		foreach($names as $name){
			if(is_string($name)){
				$result[] = $name;
			}
		}

		return $result;
	}

	public function close() : void{
		$this->config->save();
	}

	/**
	 * @return array<mixed>
	 */
	private function section(string $key) : array{
		$value = $this->config->get($key, []);

		return is_array($value) ? $value : [];
	}
}
