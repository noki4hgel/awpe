<?php

declare(strict_types=1);

namespace practice\entity;

use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\player\Player;
use practice\util\PlayerUtil;

/**
 * Ender pearl that teleports without announcing it.
 *
 * Vanilla plays the enderman particles at both ends of the throw plus a sound
 * loud enough to cover the fight. In a duel that is noise on top of a move that
 * is already obvious, so this one only moves the thrower - and it moves him in
 * one step, without the slide viewers would otherwise see.
 *
 * The landing damage is left to vanilla: the core cancels it through
 * "combat.disable-ender-pearl-damage", which a server may well want to keep.
 */
final class PracticeEnderPearl extends EnderPearl{

	/** Vanilla's charge for landing a pearl, in half-hearts. */
	private const LANDING_DAMAGE = 5;

	/**
	 * Vanilla builds projectiles deep inside the item code with no way to pass
	 * anything in, so the switch is installed once at plugin enable.
	 */
	private static bool $silentTeleport = true;

	public static function setSilentTeleport(bool $silent) : void{
		self::$silentTeleport = $silent;
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		if(!self::$silentTeleport){
			parent::onHit($event);
			return;
		}

		$owner = $this->getOwningEntity();
		if($owner === null){
			return;
		}

		$target = $event->getRayTraceResult()->getHitVector();
		if($owner instanceof Player){
			PlayerUtil::teleportInstantly($owner, $target);
		}else{
			$owner->teleport($target);
		}
		$owner->attack(new EntityDamageEvent($owner, EntityDamageEvent::CAUSE_FALL, self::LANDING_DAMAGE));
	}
}
