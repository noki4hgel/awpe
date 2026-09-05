<?php

declare(strict_types=1);

namespace practice\cooldown;

use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use practice\PracticeConfig;
use practice\util\Placeholder;

/**
 * Applies the configured item cooldowns.
 *
 * The throw is blocked here rather than by the vanilla cooldown because the
 * vanilla one is overwritten by Player::useHeldItem() right after the event.
 * The client-side overlay is re-synced a tick later so the player still sees
 * the timer on the item.
 */
final class CooldownListener implements Listener{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly CooldownManager $cooldowns,
		private readonly ItemCooldowns $items,
		private readonly TaskScheduler $scheduler
	){
	}

	/**
	 * @priority LOWEST
	 */
	public function onItemUse(PlayerItemUseEvent $event) : void{
		$player = $event->getPlayer();
		$item = $event->getItem();

		$tag = $this->items->getTag($item);
		if($tag === null){
			return;
		}

		if($this->cooldowns->has($player, $tag)){
			$event->cancel();
			$this->sendRemaining($player, $tag);
		}
	}

	/**
	 * Starts the cooldown only once a projectile has actually left the hand, so
	 * a cancelled throw does not cost the player his timer.
	 */
	public function onProjectileLaunch(ProjectileLaunchEvent $event) : void{
		$thrower = $event->getEntity()->getOwningEntity();
		if(!$thrower instanceof Player){
			return;
		}

		$item = $thrower->getInventory()->getItemInHand();
		$ticks = $this->items->getTicks($item);
		if($ticks <= 0){
			return;
		}

		$tag = $this->items->getTag($item);
		if($tag === null){
			return;
		}

		$this->cooldowns->set($thrower, $tag, $ticks);
		$this->syncClientCooldown($thrower, $item, $ticks);
	}

	/**
	 * Vanilla resets the item cooldown to the item's own value after the use
	 * event returns, so the real duration has to be pushed a tick later.
	 */
	private function syncClientCooldown(Player $player, Item $item, int $ticks) : void{
		$this->scheduler->scheduleDelayedTask(new ClosureTask(static function() use ($player, $item, $ticks) : void{
			if($player->isConnected()){
				$player->resetItemCooldown($item, $ticks - 1);
			}
		}), 1);
	}

	private function sendRemaining(Player $player, string $tag) : void{
		$message = $this->config->getMessage("cooldown-active");
		if($message === ""){
			return;
		}

		$player->sendActionBarMessage(Placeholder::apply($message, [
			"item" => $tag,
			"seconds" => $this->cooldowns->getRemainingSeconds($player, $tag)
		]));
	}
}
