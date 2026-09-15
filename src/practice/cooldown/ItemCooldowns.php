<?php

declare(strict_types=1);

namespace practice\cooldown;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use function in_array;
use function is_int;
use function is_string;

/**
 * Which items have a custom cooldown and how long it lasts.
 *
 * Built from the cooldowns.items config block, keyed by item state id so two
 * variants of the same item (potion types, for example) stay distinct.
 */
final class ItemCooldowns{

	/** @var array<int, int> item state id => cooldown in ticks */
	private array $ticks = [];

	/** @var array<int, string> item state id => cooldown tag */
	private array $tags = [];

	/** @var array<int, Item> item state id => the item, for telling the client */
	private array $items = [];

	private const TICKS_PER_SECOND = 20;

	/**
	 * @param array<string, mixed> $config item name => cooldown in seconds
	 *
	 * @return string[] names that could not be resolved
	 */
	public function loadFromConfig(array $config) : array{
		$this->ticks = [];
		$this->tags = [];
		$this->items = [];
		$failed = [];

		foreach($config as $name => $seconds){
			if(!is_string($name) || !is_int($seconds)){
				$failed[] = (string) $name;
				continue;
			}
			if($seconds <= 0){
				//An explicit 0 disables the cooldown; that is not an error.
				continue;
			}

			$item = StringToItemParser::getInstance()->parse($name);
			if($item === null){
				$failed[] = $name;
				continue;
			}

			$this->ticks[$item->getStateId()] = $seconds * self::TICKS_PER_SECOND;
			$this->tags[$item->getStateId()] = $name;
			$this->items[$item->getStateId()] = $item;
		}

		return $failed;
	}

	public function getTicks(Item $item) : int{
		return $this->ticks[$item->getStateId()] ?? 0;
	}

	public function getTag(Item $item) : ?string{
		return $this->tags[$item->getStateId()] ?? null;
	}

	public function has(Item $item) : bool{
		return isset($this->ticks[$item->getStateId()]);
	}

	/**
	 * Every item that has a cooldown, so the client's per-item timers can be
	 * cleared when a fresh kit is handed out.
	 *
	 * @return Item[]
	 */
	public function getItems() : array{
		return $this->items;
	}

	/**
	 * Whether a cooldown tag belongs to an item rather than to something
	 * internal like the chat limiter.
	 */
	public function isItemTag(string $tag) : bool{
		return in_array($tag, $this->tags, true);
	}
}
