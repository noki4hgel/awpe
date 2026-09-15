<?php

declare(strict_types=1);

namespace practice\kit;

use function array_keys;
use function is_array;
use function is_string;

/**
 * Every kit defined under "kits" in config.yml, shared by all modes.
 *
 * FFA arenas and duel ladders both refer to kits by id, so the same NoDebuff
 * loadout is guaranteed to be the same in either.
 */
final class KitRegistry{

	/** @var array<string, Kit> */
	private array $kits = [];

	/**
	 * @param array<mixed> $config
	 */
	public function loadFromConfig(array $config) : void{
		$this->kits = [];
		foreach($config as $id => $data){
			if(is_string($id) && is_array($data)){
				$this->kits[$id] = KitFactory::fromConfig($id, $data);
			}
		}
	}

	public function get(string $id) : ?Kit{
		return $this->kits[$id] ?? null;
	}

	/**
	 * @return string[]
	 */
	public function ids() : array{
		return array_keys($this->kits);
	}
}
