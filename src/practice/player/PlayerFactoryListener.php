<?php

declare(strict_types=1);

namespace practice\player;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCreationEvent;

/**
 * Makes the server build PracticePlayer instances instead of plain Players.
 *
 * Registered on its own so the substitution is obvious and easy to remove.
 */
final class PlayerFactoryListener implements Listener{

	public function onPlayerCreation(PlayerCreationEvent $event) : void{
		$event->setPlayerClass(PracticePlayer::class);
	}
}
