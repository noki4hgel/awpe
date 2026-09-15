<?php

declare(strict_types=1);

namespace practice\player;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use practice\ffa\KnockbackProfile;
use function mt_getrandmax;
use function mt_rand;
use function sqrt;

/**
 * Player subclass used by the practice core.
 *
 * It exists for exactly one reason: vanilla ties the vertical knockback to the
 * horizontal force, so a mode cannot tune the two apart without replacing
 * Living::knockBack(). Everything else stays in the managers.
 */
final class PracticePlayer extends Player{

	private KnockbackProfile $knockbackProfile;

	public function getKnockbackProfile() : KnockbackProfile{
		return $this->knockbackProfile ??= new KnockbackProfile();
	}

	public function setKnockbackProfile(KnockbackProfile $profile) : void{
		$this->knockbackProfile = $profile;
	}

	/**
	 * Forgets every vanilla item cooldown.
	 *
	 * The core pushes its own durations into this map so the client draws the
	 * timer on the item, which also means vanilla keeps refusing the item until
	 * that timer runs out. Handing out a fresh kit has to wipe it, and nothing
	 * in the public Player API can.
	 */
	public function clearItemCooldowns() : void{
		$this->usedItemsCooldown = [];
	}

	/**
	 * Same shape as the vanilla implementation, except the horizontal push and
	 * the vertical push come from the active profile instead of both being
	 * derived from $force.
	 */
	public function knockBack(float $x, float $z, float $force = self::DEFAULT_KNOCKBACK_FORCE, ?float $verticalLimit = self::DEFAULT_KNOCKBACK_VERTICAL_LIMIT) : void{
		$length = sqrt($x * $x + $z * $z);
		if($length <= 0){
			return;
		}

		if(mt_rand() / mt_getrandmax() <= $this->knockbackResistanceAttr->getValue()){
			return;
		}

		$profile = $this->getKnockbackProfile();
		$scale = 1 / $length;

		$motionX = $this->motion->x / 2 + $x * $scale * $profile->getHorizontal();
		$motionY = $this->motion->y / 2 + $profile->getVertical();
		$motionZ = $this->motion->z / 2 + $z * $scale * $profile->getHorizontal();

		if($motionY > $profile->getVertical()){
			$motionY = $profile->getVertical();
		}

		$this->setMotion(new Vector3($motionX, $motionY, $motionZ));
	}
}
