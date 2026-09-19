<?php

declare(strict_types=1);

namespace practice\entity;

use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\item\PotionType;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;

/**
 * Registers the core's entities with the server.
 *
 * The save names shadow the vanilla ones so a chunk saved with one of these
 * entities still loads; they are all short-lived projectiles and effects, so
 * nothing of value is lost either way.
 */
final class EntityRegistry{

	private function __construct(){
		//NOOP
	}

	public static function register() : void{
		$factory = EntityFactory::getInstance();

		$factory->register(
			PracticeSplashPotion::class,
			static fn(World $world, CompoundTag $nbt) : PracticeSplashPotion => new PracticeSplashPotion(
				EntityDataHelper::parseLocation($nbt, $world),
				null,
				PotionType::WATER,
				$nbt
			),
			["awpe:splash_potion"]
		);

		$factory->register(
			PracticeEnderPearl::class,
			static fn(World $world, CompoundTag $nbt) : PracticeEnderPearl => new PracticeEnderPearl(
				EntityDataHelper::parseLocation($nbt, $world),
				null,
				$nbt
			),
			["awpe:ender_pearl"]
		);

		$factory->register(
			PracticeLightningBolt::class,
			static fn(World $world, CompoundTag $nbt) : PracticeLightningBolt => new PracticeLightningBolt(
				EntityDataHelper::parseLocation($nbt, $world),
				$nbt
			),
			["awpe:lightning_bolt"]
		);
	}
}
