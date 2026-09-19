<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\item\EnderPearl as VanillaEnderPearl;
use pocketmine\player\Player;
use practice\entity\PracticeEnderPearl as PracticeEnderPearlEntity;

/**
 * Ender pearl item that throws the practice-tuned projectile.
 *
 * Identical to the vanilla item in every other respect - same type id, stack
 * size, throw force and cooldown tag - so kits and the cooldown manager cannot
 * tell the two apart.
 */
final class PracticeEnderPearl extends VanillaEnderPearl{

	protected function createEntity(Location $location, Player $thrower) : Throwable{
		return new PracticeEnderPearlEntity($location, $thrower);
	}
}
