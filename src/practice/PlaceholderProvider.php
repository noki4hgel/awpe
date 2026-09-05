<?php

declare(strict_types=1);

namespace practice;

use pocketmine\Server;
use practice\combat\CombatManager;
use practice\ffa\FFAManager;
use practice\session\Session;
use practice\util\DeviceUtil;
use function ceil;
use function count;

/**
 * Single source for the {placeholders} every configurable text may use.
 *
 * Scoreboard, boss bar, nametags and chat messages all format through this, so
 * a placeholder added here is immediately available everywhere.
 */
final class PlaceholderProvider{

	public function __construct(
		private readonly Server $server,
		private readonly PracticeConfig $config,
		private readonly FFAManager $ffa,
		private readonly CombatManager $combat
	){
	}

	/**
	 * @return array<string, string|int|float>
	 */
	public function forSession(Session $session) : array{
		$player = $session->getPlayer();
		$arena = $this->ffa->getArenaOf($session);

		return [
			"player" => $player->getName(),
			"online" => count($this->server->getOnlinePlayers()),
			"max_online" => $this->server->getMaxPlayers(),
			"kills" => $session->getKills(),
			"deaths" => $session->getDeaths(),
			"ping" => $session->getPing(),
			"device" => $this->getDeviceName($session),
			"health" => (int) ceil($player->getHealth()),
			"max_health" => $player->getMaxHealth(),
			"arena" => $arena?->getId() ?? "",
			"arena_display" => $arena?->getDisplayName() ?? "",
			"arena_players" => $arena?->count() ?? 0,
			//Seconds left on the combat tag; 0 when the player is free to leave.
			"combat" => $this->combat->getRemainingSeconds($player),
			"pots" => $arena?->countPots($player) ?? 0
		];
	}

	public function getDeviceName(Session $session) : string{
		$key = $session->getDeviceKey();
		$fallback = $this->config->getString("nametag.devices." . DeviceUtil::UNKNOWN_KEY, "Unknown");

		return $this->config->getString("nametag.devices." . $key, $fallback);
	}
}
