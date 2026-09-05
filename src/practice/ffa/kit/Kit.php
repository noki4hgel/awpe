<?php

declare(strict_types=1);

namespace practice\ffa\kit;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * An immutable loadout. Items are cloned on the way out so a kit can be handed
 * to any number of players without them sharing item instances.
 */
final class Kit{

	public const SLOT_HELMET = "helmet";
	public const SLOT_CHESTPLATE = "chestplate";
	public const SLOT_LEGGINGS = "leggings";
	public const SLOT_BOOTS = "boots";

	/**
	 * @param array<string, Item> $armor    keyed by SLOT_* constants
	 * @param array<int, Item>    $hotbar   inventory slot => item
	 * @param Item[]              $extra    appended to the first free slots
	 * @param EffectInstance[]    $effects
	 */
	public function __construct(
		private readonly string $id,
		private readonly array $armor,
		private readonly array $hotbar,
		private readonly array $extra,
		private readonly array $effects,
		private readonly ?Item $filler
	){
	}

	public function getId() : string{
		return $this->id;
	}

	/**
	 * Wipes the player's inventory and effects, then hands out the loadout.
	 */
	public function apply(Player $player) : void{
		$inventory = $player->getInventory();
		$armorInventory = $player->getArmorInventory();

		$inventory->clearAll();
		$armorInventory->clearAll();
		$player->getOffHandInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getCraftingGrid()->clearAll();
		$player->getEffects()->clear();

		foreach($this->armor as $slot => $item){
			match($slot){
				self::SLOT_HELMET => $armorInventory->setHelmet(clone $item),
				self::SLOT_CHESTPLATE => $armorInventory->setChestplate(clone $item),
				self::SLOT_LEGGINGS => $armorInventory->setLeggings(clone $item),
				self::SLOT_BOOTS => $armorInventory->setBoots(clone $item),
				default => null
			};
		}

		foreach($this->hotbar as $slot => $item){
			$inventory->setItem($slot, clone $item);
		}

		foreach($this->extra as $item){
			$inventory->addItem(clone $item);
		}

		if($this->filler !== null){
			for($slot = 0, $size = $inventory->getSize(); $slot < $size; $slot++){
				if($inventory->isSlotEmpty($slot)){
					$inventory->setItem($slot, clone $this->filler);
				}
			}
		}

		foreach($this->effects as $effect){
			$player->getEffects()->add(clone $effect);
		}
	}
}
