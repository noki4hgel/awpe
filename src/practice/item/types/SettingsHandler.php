<?php

declare(strict_types=1);

namespace practice\item\types;

use practice\item\LobbyItemHandler;
use practice\session\Session;
use practice\settings\SettingsMenu;

/**
 * Lobby book — opens the settings menu.
 */
final class SettingsHandler implements LobbyItemHandler{

	public const ACTION_ID = "settings";

	public function __construct(private readonly SettingsMenu $menu){
	}

	public function getActionId() : string{
		return self::ACTION_ID;
	}

	public function onUse(Session $session) : void{
		$this->menu->sendSectionMenu($session);
	}
}
