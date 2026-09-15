<?php

declare(strict_types=1);

namespace practice\duel;

use practice\ffa\KnockbackProfile;
use practice\kit\KitRegistry;
use function array_filter;
use function is_array;
use function is_string;
use function strtolower;

/**
 * The duel modes defined in config.yml, in the order they appear there — which
 * is also the order the menus show them in.
 */
final class LadderRegistry{

	/** @var array<string, Ladder> */
	private array $ladders = [];

	/**
	 * @param array<mixed> $config
	 *
	 * @return string[] ids that were skipped, usually for naming an unknown kit
	 */
	public function loadFromConfig(array $config, KitRegistry $kits, KnockbackProfile $defaultKnockback, string $defaultPotItem) : array{
		$this->ladders = [];
		$failed = [];

		foreach($config as $id => $data){
			if(!is_string($id) || !is_array($data)){
				$failed[] = (string) $id;
				continue;
			}

			$ladder = Ladder::fromConfig(strtolower($id), $data, $kits, $defaultKnockback, $defaultPotItem);
			if($ladder === null){
				$failed[] = $id;
				continue;
			}

			$this->ladders[$ladder->getId()] = $ladder;
		}

		return $failed;
	}

	public function get(string $id) : ?Ladder{
		return $this->ladders[strtolower($id)] ?? null;
	}

	/**
	 * @return array<string, Ladder>
	 */
	public function all() : array{
		return $this->ladders;
	}

	/**
	 * @return array<string, Ladder>
	 */
	public function ranked() : array{
		return array_filter($this->ladders, static fn(Ladder $ladder) => $ladder->isRankedAllowed());
	}

	/**
	 * Keeps only the modes an arena actually exists for, so nobody can queue
	 * for a mode that could never start.
	 *
	 * @param \Closure(string) : bool $isPlayable
	 */
	public function retain(\Closure $isPlayable) : void{
		$this->ladders = array_filter($this->ladders, static fn(Ladder $ladder) => $isPlayable($ladder->getId()));
	}
}
