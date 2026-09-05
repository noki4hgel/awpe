<?php

declare(strict_types=1);

namespace practice\session;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use practice\bossbar\BossBarManager;
use practice\combat\CombatManager;
use practice\cooldown\CooldownManager;
use practice\ffa\FFAManager;
use practice\lobby\LobbyManager;
use practice\nametag\NameTagManager;
use practice\PracticeConfig;
use practice\scoreboard\ScoreboardManager;
use practice\util\Placeholder;

/**
 * Opens and closes sessions, and owns the join/quit broadcast.
 */
final class SessionListener implements Listener{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly SessionManager $sessions,
		private readonly LobbyManager $lobby,
		private readonly FFAManager $ffa,
		private readonly CombatManager $combat,
		private readonly CooldownManager $cooldowns,
		private readonly ScoreboardManager $scoreboards,
		private readonly BossBarManager $bossBars,
		private readonly NameTagManager $nameTags
	){
	}

	public function onJoin(PlayerJoinEvent $event) : void{
		$player = $event->getPlayer();
		$session = $this->sessions->open($player);

		$event->setJoinMessage(Placeholder::apply($this->config->getMessage("join"), ["player" => $player->getName()]));

		$this->lobby->join($session);

		//Draw the displays immediately instead of waiting for the next heartbeat.
		$this->scoreboards->update($session);
		$this->bossBars->update($session);
		$this->nameTags->update($session);
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();

		$event->setQuitMessage(Placeholder::apply($this->config->getMessage("quit"), ["player" => $player->getName()]));

		$session = $this->sessions->get($player);
		if($session !== null){
			$this->ffa->leaveArenaOnly($session);
		}

		//Timers are keyed by the connection, so they must not outlive it.
		$this->combat->untag($player);
		$this->cooldowns->clear($player);
		$this->sessions->close($player);
	}

	/**
	 * Players are sent back to the lobby by the combat handler, so a vanilla
	 * respawn should never leave anyone in an arena world.
	 */
	public function onRespawn(PlayerRespawnEvent $event) : void{
		$session = $this->sessions->get($event->getPlayer());
		if($session === null || $session->isInLobby()){
			return;
		}

		$this->ffa->leave($session);
	}
}
