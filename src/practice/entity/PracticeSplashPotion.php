<?php

declare(strict_types=1);

namespace practice\entity;

use pocketmine\color\Color;
use pocketmine\entity\effect\InstantEffect;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\PotionType;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\particle\PotionSplashParticle;
use pocketmine\world\sound\PotionSplashSound;
use function count;
use function round;
use function sqrt;

/**
 * Splash potion with practice-server potency.
 *
 * Vanilla measures the distance from the victim's eyes and reduces the effect
 * linearly from the moment of impact, which makes a pot at your own feet heal
 * about 60%. This one measures from the feet and gives full potency inside
 * PotionSettings::getFullEffectRange(), which is what players expect on a
 * practice server.
 */
final class PracticeSplashPotion extends SplashPotion{

	/**
	 * Vanilla constructs projectiles for us deep inside the item code, with no
	 * way to pass anything in, so the tuning is installed once at plugin enable.
	 */
	private static ?PotionSettings $settings = null;

	public static function setSettings(PotionSettings $settings) : void{
		self::$settings = $settings;
	}

	public static function getSettings() : PotionSettings{
		return self::$settings ??= new PotionSettings();
	}

	public function __construct(Location $location, ?Entity $shootingEntity, PotionType $potionType, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $potionType, $nbt);

		$this->setScale(self::getSettings()->getScale());
	}

	protected function getInitialGravity() : float{
		return self::getSettings()->getGravity();
	}

	protected function getInitialDragMultiplier() : float{
		return self::getSettings()->getDrag();
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		$effects = $this->getPotionEffects();
		if(count($effects) === 0){
			//Water bottles and the like: let vanilla deal with the block effects.
			parent::onHit($event);
			return;
		}

		$settings = self::getSettings();

		$colors = [];
		foreach($effects as $effect){
			for($i = 0; $i < $effect->getEffectLevel(); ++$i){
				$colors[] = $effect->getColor();
			}
		}

		$this->getWorld()->addParticle($this->location, new PotionSplashParticle(Color::mix(...$colors)));
		$this->broadcastSound(new PotionSplashSound());

		if($this->willLinger()){
			//TODO: lingering potions, same as vanilla
			return;
		}

		$range = $settings->getRange();
		foreach($this->getWorld()->getCollidingEntities($this->boundingBox->expandedCopy($range, $range, $range), $this) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive()){
				continue;
			}

			$distanceSquared = $entity->getPosition()->distanceSquared($this->location);
			if($distanceSquared > $range ** 2){
				continue;
			}

			$potency = $settings->potencyAt(sqrt($distanceSquared));
			if($event instanceof ProjectileHitEntityEvent && $entity === $event->getEntityHit()){
				$potency = 1.0;
			}
			if($potency <= 0){
				continue;
			}

			//Fresh copies per entity, so shortening a duration cannot leak between targets.
			foreach($this->getPotionEffects() as $effect){
				if($effect->getType() instanceof InstantEffect){
					$effect->getType()->applyEffect($entity, $effect, $potency, $this);
					continue;
				}

				$duration = (int) round($effect->getDuration() * 0.75 * $potency);
				if($duration < 20){
					continue;
				}
				$effect->setDuration($duration);
				$entity->getEffects()->add($effect);
			}
		}
	}
}
