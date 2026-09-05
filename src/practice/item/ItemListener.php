<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;
use practice\session\SessionManager;

/**
 * Turns a tap on a tagged lobby item into its action.
 *
 * Both handlers run at LOWEST so they fire before the lobby protection listener
 * cancels the interaction.
 */
final class ItemListener implements Listener{

	public function __construct(
		private readonly LobbyItemRegistry $registry,
		private readonly SessionManager $sessions
	){
	}

	/**
	 * @priority LOWEST
	 */
	public function onItemUse(PlayerItemUseEvent $event) : void{
		if($this->handle($event->getPlayer(), $event->getItem())){
			$event->cancel();
		}
	}

	/**
	 * @priority LOWEST
	 */
	public function onInteract(PlayerInteractEvent $event) : void{
		if($this->handle($event->getPlayer(), $event->getItem())){
			$event->cancel();
		}
	}

	private function handle(Player $player, Item $item) : bool{
		$session = $this->sessions->get($player);
		if($session === null){
			return false;
		}

		return $this->registry->use($session, $item);
	}
}
