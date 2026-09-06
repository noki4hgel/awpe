<?php

declare(strict_types=1);

namespace practice\rank;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use practice\session\SessionManager;

/**
 * Puts the sender's rank format on his chat messages and drops the permission
 * attachment when he leaves.
 */
final class RankListener implements Listener{

	public function __construct(
		private readonly RankManager $ranks,
		private readonly SessionManager $sessions
	){
	}

	public function onChat(PlayerChatEvent $event) : void{
		$session = $this->sessions->get($event->getPlayer());
		if($session === null){
			return;
		}

		$rank = $this->ranks->get($session);
		if($rank === null){
			return;
		}

		$event->setFormatter(new RankChatFormatter($rank->getChatFormat()));
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		$this->ranks->revoke($event->getPlayer());
	}
}
