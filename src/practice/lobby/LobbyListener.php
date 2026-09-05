<?php

declare(strict_types=1);

namespace practice\lobby;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use practice\session\SessionManager;

/**
 * Enforces the lobby rules configured under lobby.protection.
 *
 * Every check goes through LobbyManager::shouldProtect(), so a rule can be
 * switched off in the config without touching this class.
 */
final class LobbyListener implements Listener{

	public function __construct(
		private readonly LobbyManager $lobby,
		private readonly SessionManager $sessions
	){
	}

	public function onBlockBreak(BlockBreakEvent $event) : void{
		if($this->lobby->shouldProtect($event->getPlayer(), LobbyManager::RULE_BLOCK_BREAK)){
			$event->cancel();
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) : void{
		if($this->lobby->shouldProtect($event->getPlayer(), LobbyManager::RULE_BLOCK_PLACE)){
			$event->cancel();
		}
	}

	public function onDropItem(PlayerDropItemEvent $event) : void{
		if($this->lobby->shouldProtect($event->getPlayer(), LobbyManager::RULE_ITEM_DROP)){
			$event->cancel();
		}
	}

	public function onItemPickup(EntityItemPickupEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof Player && $this->lobby->shouldProtect($entity, LobbyManager::RULE_ITEM_PICKUP)){
			$event->cancel();
		}
	}

	public function onInventoryTransaction(InventoryTransactionEvent $event) : void{
		$player = $event->getTransaction()->getSource();
		if($this->lobby->shouldProtect($player, LobbyManager::RULE_INVENTORY_TRANSACTION)){
			$event->cancel();
		}
	}

	public function onCraft(CraftItemEvent $event) : void{
		if($this->lobby->shouldProtect($event->getPlayer(), LobbyManager::RULE_INVENTORY_TRANSACTION)){
			$event->cancel();
		}
	}

	/**
	 * Blocks both taking damage in the lobby and hitting anyone from it.
	 */
	public function onEntityDamage(EntityDamageEvent $event) : void{
		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();
			if($damager instanceof Player && $this->lobby->shouldProtect($damager, LobbyManager::RULE_PVP)){
				$event->cancel();
				return;
			}
		}

		$entity = $event->getEntity();
		if($entity instanceof Player && $this->lobby->shouldProtect($entity, LobbyManager::RULE_DAMAGE)){
			$event->cancel();
		}
	}

	public function onExhaust(PlayerExhaustEvent $event) : void{
		$player = $event->getPlayer();
		if($player instanceof Player && $this->lobby->shouldProtect($player, LobbyManager::RULE_HUNGER)){
			$event->cancel();
		}
	}

	/**
	 * @priority HIGH
	 *
	 * Runs after the lobby item handler (registered at LOWEST) so tapping a menu
	 * item still works while ordinary block interaction stays blocked.
	 */
	public function onInteract(PlayerInteractEvent $event) : void{
		if($this->lobby->shouldProtect($event->getPlayer(), LobbyManager::RULE_INTERACT)){
			$event->cancel();
		}
	}

	/**
	 * Catches players who walk off the lobby island before the void can.
	 */
	public function onMove(PlayerMoveEvent $event) : void{
		$player = $event->getPlayer();
		$session = $this->sessions->get($player);
		if($session === null || !$session->isInLobby()){
			return;
		}

		$spawn = $this->lobby->getSpawn();
		if($spawn === null){
			return;
		}

		if($event->getTo()->getY() >= $spawn->getY() - $this->lobby->getVoidLevel()){
			return;
		}

		$this->lobby->teleport($session);
	}
}
