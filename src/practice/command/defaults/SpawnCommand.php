<?php

declare(strict_types=1);

namespace practice\command\defaults;

use practice\command\PracticeCommand;
use practice\duel\match\MatchManager;
use practice\duel\queue\QueueManager;
use practice\ffa\FFAManager;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;

/**
 * /spawn — leaves whatever the player is doing and returns him to the lobby.
 */
final class SpawnCommand extends PracticeCommand{

	public const NAME = "spawn";
	public const PERMISSION = "awpe.command.spawn";

	public function __construct(
		PracticeConfig $config,
		SessionManager $sessions,
		private readonly FFAManager $ffa,
		private readonly MatchManager $matches,
		private readonly QueueManager $queue
	){
		parent::__construct(self::NAME, "Return to the lobby", self::PERMISSION, $config, $sessions);
		$this->setAliases(["lobby", "hub"]);
	}

	/**
	 * From a match this is a surrender - the opponent wins. The combat log still
	 * blocks it mid-exchange, so it cannot be used to dodge a losing trade.
	 */
	protected function run(Session $session, array $args) : void{
		if($this->matches->isInMatch($session)){
			$this->matches->forfeit($session, false);
			$session->getPlayer()->sendMessage($this->config->getMessage("duel-you-forfeited"));
			return;
		}

		if($session->isInLobby()){
			//Leaving the queue is the one thing /spawn can still do from the lobby.
			if(!$this->queue->leave($session)){
				$session->getPlayer()->sendMessage($this->config->getMessage("already-in-lobby"));
			}
			return;
		}

		$this->ffa->leave($session);
		$session->getPlayer()->sendMessage($this->config->getMessage("spawn-teleported"));
	}
}
