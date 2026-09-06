<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\item\SplashPotion as VanillaSplashPotion;
use pocketmine\player\Player;
use practice\entity\PracticeSplashPotion as PracticeSplashPotionEntity;

/**
 * Splash potion item that throws the practice-tuned projectile.
 *
 * Identical to the vanilla item in every other respect — same type and state id
 * — so kits, inventories and the pot counter cannot tell the two apart.
 */
final class PracticeSplashPotion extends VanillaSplashPotion{

	protected function createEntity(Location $location, Player $thrower) : Throwable{
		return new PracticeSplashPotionEntity($location, $thrower, $this->getType());
	}
}
