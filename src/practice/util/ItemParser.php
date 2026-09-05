<?php

declare(strict_types=1);

namespace practice\util;

use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\ByteTag;
use function is_array;
use function is_int;
use function is_string;
use function max;

/**
 * Builds an Item out of a configuration block, so kits and lobby items can be
 * described entirely in config.yml.
 *
 * Recognised keys:
 *   item          vanilla name, e.g. "diamond_sword", "strong_healing_splash_potion"
 *   count         stack size (default 1)
 *   name          custom display name
 *   lore          list of lore lines
 *   unbreakable   true to make a tool/armour piece indestructible
 *   locked        true to pin the item into its slot client-side
 *   enchantments  map of enchantment name => level
 */
final class ItemParser{

	/** Vanilla component that stops the client from moving an item out of its slot. */
	private const TAG_ITEM_LOCK = "minecraft:item_lock";
	private const ITEM_LOCK_IN_SLOT = 1;

	private function __construct(){
		//NOOP
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return Item|null null when the item name is missing or unknown
	 */
	public static function parse(array $data) : ?Item{
		$name = $data["item"] ?? null;
		if(!is_string($name)){
			return null;
		}

		$item = StringToItemParser::getInstance()->parse($name);
		if($item === null){
			return null;
		}

		$count = $data["count"] ?? 1;
		$item->setCount(is_int($count) ? max(1, $count) : 1);

		if(is_string($data["name"] ?? null)){
			$item->setCustomName($data["name"]);
		}

		if(is_array($data["lore"] ?? null)){
			$lore = [];
			foreach($data["lore"] as $line){
				if(is_string($line)){
					$lore[] = $line;
				}
			}
			$item->setLore($lore);
		}

		if(($data["unbreakable"] ?? false) === true && $item instanceof Durable){
			$item->setUnbreakable();
		}

		if(($data["locked"] ?? false) === true){
			$item->getNamedTag()->setTag(self::TAG_ITEM_LOCK, new ByteTag(self::ITEM_LOCK_IN_SLOT));
		}

		if(is_array($data["enchantments"] ?? null)){
			foreach($data["enchantments"] as $enchantmentName => $level){
				if(!is_string($enchantmentName) || !is_int($level) || $level < 1){
					continue;
				}
				$enchantment = StringToEnchantmentParser::getInstance()->parse($enchantmentName);
				if($enchantment !== null){
					$item->addEnchantment(new EnchantmentInstance($enchantment, $level));
				}
			}
		}

		return $item;
	}
}
