<?php

declare(strict_types=1);

namespace practice\item\types;

use practice\ffa\FFAManager;
use practice\item\LobbyItemHandler;
use practice\PracticeConfig;
use practice\session\Session;

/**
 * Lobby axe — opens the free-for-all arena picker.
 */
final class FFASelectorHandler implements LobbyItemHandler{

	public const ACTION_ID = "ffa-selector";

	public function __construct(
		private readonly FFAManager $ffa,
		private readonly PracticeConfig $config
	){
	}

	public function getActionId() : string{
		return self::ACTION_ID;
	}

	public function onUse(Session $session) : void{
		if(!$session->isInLobby()){
			$session->getPlayer()->sendMessage($this->config->getMessage("not-in-lobby"));
			return;
		}

		$this->ffa->sendSelectorForm($session);
	}
}
