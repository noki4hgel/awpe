<?php

declare(strict_types=1);

namespace practice\item\types;

use practice\item\LobbyItemHandler;
use practice\PracticeConfig;
use practice\session\Session;

/**
 * Lobby clock — reserved for private/duel arenas.
 *
 * The slot already exists so the hotbar layout does not shift once the feature
 * lands; until then it only reports that it is not ready.
 */
final class PrivateArenaHandler implements LobbyItemHandler{

	public const ACTION_ID = "private-arenas";

	public function __construct(private readonly PracticeConfig $config){
	}

	public function getActionId() : string{
		return self::ACTION_ID;
	}

	public function onUse(Session $session) : void{
		$session->getPlayer()->sendMessage($this->config->getMessage("soon"));
	}
}
