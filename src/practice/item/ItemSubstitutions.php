<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\item\EnderPearl as VanillaEnderPearl;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\SplashPotion as VanillaSplashPotion;

/**
 * Swaps vanilla items for the core's own subclasses.
 *
 * The parser that reads config.yml can only produce vanilla items, but a few of
 * them need practice behaviour. Doing the swap in one place keeps that fact out
 * of the parser and out of every kit.
 */
final class ItemSubstitutions{

	private function __construct(){
		//NOOP
	}

	/**
	 * Returns the practice version of an item, or the item unchanged when there
	 * is nothing to swap. Custom name, lore and NBT are carried over.
	 */
	public static function convert(Item $item) : Item{
		if($item instanceof PracticeSplashPotion || $item instanceof PracticeEnderPearl){
			return $item;
		}

		if($item instanceof VanillaEnderPearl){
			$replacement = new PracticeEnderPearl(new ItemIdentifier(ItemTypeIds::ENDER_PEARL), $item->getVanillaName());
			$replacement->setCount($item->getCount());
			$replacement->setNamedTag(clone $item->getNamedTag());

			return $replacement;
		}

		if($item instanceof VanillaSplashPotion){
			$replacement = new PracticeSplashPotion(new ItemIdentifier(ItemTypeIds::SPLASH_POTION), $item->getVanillaName());
			$replacement->setType($item->getType());
			$replacement->setCount($item->getCount());
			$replacement->setNamedTag(clone $item->getNamedTag());

			return $replacement;
		}

		return $item;
	}
}
