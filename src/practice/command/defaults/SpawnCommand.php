<?php

declare(strict_types=1);

namespace practice\command\defaults;

use practice\command\PracticeCommand;
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
		private readonly FFAManager $ffa
	){
		parent::__construct(self::NAME, "Return to the lobby", self::PERMISSION, $config, $sessions);
		$this->setAliases(["lobby", "hub"]);
	}

	protected function run(Session $session, array $args) : void{
		if($session->isInLobby()){
			$session->getPlayer()->sendMessage($this->config->getMessage("already-in-lobby"));
			return;
		}

		$this->ffa->leave($session);
		$session->getPlayer()->sendMessage($this->config->getMessage("spawn-teleported"));
	}
}
