<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\item\Item;

/**
 * A configured lobby hotbar entry: which slot it lives in and the item stack to
 * put there, already tagged with the action it triggers.
 */
final class LobbyItem{

	public function __construct(
		private readonly string $actionId,
		private readonly int $slot,
		private readonly Item $item
	){
	}

	public function getActionId() : string{
		return $this->actionId;
	}

	public function getSlot() : int{
		return $this->slot;
	}

	public function getItem() : Item{
		return clone $this->item;
	}
}
