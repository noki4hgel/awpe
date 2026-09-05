<?php

declare(strict_types=1);

namespace practice\util;

use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function strtolower;

/**
 * Shared player housekeeping used whenever somebody changes state (lobby entry,
 * arena entry, respawn). Having it in one place keeps "clean slate" meaning the
 * same thing everywhere.
 */
final class PlayerUtil{

	private function __construct(){
		//NOOP
	}

	public static function reset(Player $player, GameMode $gameMode) : void{
		if(!$player->isConnected()){
			return;
		}

		$player->setGamemode($gameMode);
		$player->setAllowFlight($gameMode === GameMode::CREATIVE || $gameMode === GameMode::SPECTATOR);
		$player->setFlying(false);
		$player->setSprinting(false);
		$player->setSneaking(false);

		$player->setHealth((float) $player->getMaxHealth());

		$hungerManager = $player->getHungerManager();
		$hungerManager->setFood((float) $hungerManager->getMaxFood());
		$hungerManager->setSaturation(20.0);

		$player->getXpManager()->setXpAndProgress(0, 0.0);

		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getOffHandInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getCraftingGrid()->clearAll();
		$player->getEffects()->clear();
		$player->extinguish();
		$player->setNoClientPredictions(false);
	}

	public static function gameModeFromString(string $name, GameMode $default = GameMode::ADVENTURE) : GameMode{
		return GameMode::fromString(strtolower($name)) ?? $default;
	}
}
