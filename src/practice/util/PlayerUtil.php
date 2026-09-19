<?php

declare(strict_types=1);

namespace practice\util;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function count;
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

	/**
	 * Teleports a player without the slide everyone else would otherwise see.
	 *
	 * The teleported client is told to snap, but viewers only get a position
	 * update, and their client smooths the jump into a walk across the map -
	 * a pearl looks like the thrower moonwalking to where it landed. Vanilla
	 * has a flag for this on the entity packet, which the server deliberately
	 * leaves off because clients mishandle it; sending the player packet the
	 * viewers' own client uses for teleports works instead.
	 */
	public static function teleportInstantly(Player $player, Vector3 $to) : bool{
		if(!$player->teleport($to)){
			return false;
		}

		$viewers = $player->getViewers();
		if(count($viewers) === 0){
			return true;
		}

		$location = $player->getLocation();
		NetworkBroadcastUtils::broadcastPackets($viewers, [MovePlayerPacket::simple(
			$player->getId(),
			$player->getOffsetPosition($location),
			$location->pitch,
			$location->yaw,
			$location->yaw,
			MovePlayerPacket::MODE_TELEPORT,
			$player->isOnGround(),
			0,
			0
		)]);

		return true;
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
