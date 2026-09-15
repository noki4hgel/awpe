<?php

declare(strict_types=1);

namespace practice\duel\arena;

use function in_array;
use function min;

/**
 * A map duels are played on: a template world that gets copied for every match,
 * the two spawn points, and the modes it suits.
 *
 * Because each match gets its own copy, an arena is never "in use" — any number
 * of matches can run on the same map at once, and none of them can leave blocks
 * or items behind for the next.
 */
final class DuelArena{

	/**
	 * @param string[] $ladderIds
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $display,
		private readonly string $templateWorld,
		private readonly array $ladderIds,
		private readonly SpawnPoint $first,
		private readonly SpawnPoint $second
	){
	}

	public function getId() : string{
		return $this->id;
	}

	public function getDisplay() : string{
		return $this->display;
	}

	/**
	 * Folder name of the template under worlds/.
	 */
	public function getTemplateWorld() : string{
		return $this->templateWorld;
	}

	public function supports(string $ladderId) : bool{
		return in_array($ladderId, $this->ladderIds, true);
	}

	public function getFirstSpawn() : SpawnPoint{
		return $this->first;
	}

	public function getSecondSpawn() : SpawnPoint{
		return $this->second;
	}

	/**
	 * The lower of the two spawn heights — what "fell off" is measured against.
	 */
	public function getFloorY() : float{
		return min($this->first->getY(), $this->second->getY());
	}
}
