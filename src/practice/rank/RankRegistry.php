<?php

declare(strict_types=1);

namespace practice\rank;

use pocketmine\utils\Config;
use function array_keys;
use function count;
use function is_array;
use function is_string;
use function strtolower;
use function uasort;

/**
 * The ranks defined in ranks.yml, ordered by priority.
 *
 * The lowest-priority rank is the default one, so a server can rename or
 * restructure its ladder without any code change.
 */
final class RankRegistry{

	/** @var array<string, Rank> id (lowercase) => rank */
	private array $ranks = [];

	/**
	 * @return string[] ids that could not be read
	 */
	public function loadFromConfig(Config $config) : array{
		$this->ranks = [];
		$failed = [];

		foreach($config->getAll() as $id => $data){
			if(!is_string($id) || !is_array($data)){
				$failed[] = (string) $id;
				continue;
			}

			$this->ranks[strtolower($id)] = Rank::fromConfig($id, $data);
		}

		//Lowest priority first, so the default rank is simply the first entry.
		uasort($this->ranks, static fn(Rank $a, Rank $b) => $a->getPriority() <=> $b->getPriority());

		return $failed;
	}

	public function get(?string $id) : ?Rank{
		return $id === null ? null : ($this->ranks[strtolower($id)] ?? null);
	}

	public function getDefault() : ?Rank{
		foreach($this->ranks as $rank){
			return $rank;
		}

		return null;
	}

	/**
	 * @return array<string, Rank>
	 */
	public function all() : array{
		return $this->ranks;
	}

	/**
	 * @return string[]
	 */
	public function ids() : array{
		return array_keys($this->ranks);
	}

	public function count() : int{
		return count($this->ranks);
	}
}
