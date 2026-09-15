<?php

declare(strict_types=1);

namespace practice\kit;

use pocketmine\player\Player;
use practice\cooldown\CooldownManager;
use practice\cooldown\ItemCooldowns;
use practice\player\PracticePlayer;

/**
 * The one way a kit reaches a player.
 *
 * Arena entry, /rekit, the killer's refill and duels all go through here, so a
 * fresh kit always means the same thing: full inventory, full health and food,
 * and no item cooldown left over from the previous life.
 *
 * Clearing the cooldown takes three steps, because it lives in three places:
 * the core's own timer, the vanilla map the core syncs so the client can draw
 * the timer, and that drawing on the client itself.
 */
final class KitIssuer{

	public function __construct(
		private readonly CooldownManager $cooldowns,
		private readonly ItemCooldowns $itemCooldowns
	){
	}

	public function issue(Player $player, Kit $kit) : void{
		if(!$player->isConnected()){
			return;
		}

		$kit->apply($player);

		$player->setHealth((float) $player->getMaxHealth());
		$player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
		$player->extinguish();

		$this->clearItemCooldowns($player);
	}

	/**
	 * Forgets every item cooldown a player is waiting on. Internal timers such as
	 * the chat limiter share the same manager and are left alone.
	 */
	public function clearItemCooldowns(Player $player) : void{
		foreach($this->cooldowns->getRunningTags($player) as $tag => $remaining){
			if($this->itemCooldowns->isItemTag($tag)){
				$this->cooldowns->clear($player, $tag);
			}
		}

		if($player instanceof PracticePlayer){
			$player->clearItemCooldowns();
		}

		//A zero-length cooldown makes the client drop the timer drawn on the item.
		foreach($this->itemCooldowns->getItems() as $item){
			$player->getNetworkSession()->onItemCooldownChanged($item, 0);
		}
	}
}
