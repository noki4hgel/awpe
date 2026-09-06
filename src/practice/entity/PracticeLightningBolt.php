<?php

declare(strict_types=1);

namespace practice\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

/**
 * Cosmetic lightning strike shown where somebody dies.
 *
 * It is a bare visual: no fire, no damage, no weather change. Who sees it is
 * decided by the caller, so a viewer who turned the effect off in his settings
 * simply never receives the spawn.
 */
final class PracticeLightningBolt extends Entity{

	/** The client plays the strike animation over roughly a second. */
	private const LIFETIME_TICKS = 25;

	public static function getNetworkTypeId() : string{
		return EntityIds::LIGHTNING_BOLT;
	}

	/**
	 * Shows the bolt to the given viewers only.
	 *
	 * @param Player[] $viewers
	 */
	public function showTo(array $viewers) : void{
		foreach($viewers as $viewer){
			if($viewer->isConnected()){
				$this->spawnTo($viewer);
			}
		}
	}

	public function onUpdate(int $currentTick) : bool{
		parent::onUpdate($currentTick);

		if($this->ticksLived >= self::LIFETIME_TICKS){
			$this->close();
			return false;
		}

		return true;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.1, 0.1);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}
}
