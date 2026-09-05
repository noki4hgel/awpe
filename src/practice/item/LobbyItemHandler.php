<?php

declare(strict_types=1);

namespace practice\item;

use practice\session\Session;

/**
 * Behaviour bound to a lobby hotbar item. Implementations are registered in
 * LobbyItemRegistry under the same action id the config uses, so adding an item
 * never means touching the listener.
 */
interface LobbyItemHandler{

	public function getActionId() : string;

	public function onUse(Session $session) : void;
}
