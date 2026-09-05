<?php

declare(strict_types=1);

namespace practice\item\types;

use practice\item\LobbyItemHandler;
use practice\PracticeConfig;
use practice\session\Session;

/**
 * Lobby book — player settings.
 *
 * Placeholder until the settings menu exists; see PrivateArenaHandler for the
 * same reasoning about keeping the slot occupied.
 */
final class SettingsHandler implements LobbyItemHandler{

	public const ACTION_ID = "settings";

	public function __construct(private readonly PracticeConfig $config){
	}

	public function getActionId() : string{
		return self::ACTION_ID;
	}

	public function onUse(Session $session) : void{
		$session->getPlayer()->sendMessage($this->config->getMessage("soon"));
	}
}
