<?php

declare(strict_types=1);

namespace practice\item\types;

use practice\duel\DuelMenu;
use practice\item\LobbyItemHandler;
use practice\session\Session;

/**
 * Lobby clock — opens the duel menu.
 */
final class DuelsHandler implements LobbyItemHandler{

	public const ACTION_ID = "duels";

	public function __construct(private readonly DuelMenu $menu){
	}

	public function getActionId() : string{
		return self::ACTION_ID;
	}

	public function onUse(Session $session) : void{
		$this->menu->openModes($session);
	}
}
