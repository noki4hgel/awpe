<?php

declare(strict_types=1);

namespace practice\ffa;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\player\Player;

/**
 * Keeps arenas intact: no terrain edits and no kit items on the floor.
 *
 * Combat itself lives in CombatListener; this class only guards the arena.
 */
final class FFAListener implements Listener{

	public function __construct(private readonly FFAManager $ffa){
	}

	public function onBlockBreak(BlockBreakEvent $event) : void{
		if($this->isInArena($event->getPlayer())){
			$event->cancel();
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) : void{
		if($this->isInArena($event->getPlayer())){
			$event->cancel();
		}
	}

	public function onDropItem(PlayerDropItemEvent $event) : void{
		if($this->isInArena($event->getPlayer())){
			$event->cancel();
		}
	}

	/**
	 * True for a player fighting in an arena, or standing in an arena world for
	 * any other reason (an operator flying around, for instance).
	 */
	private function isInArena(Player $player) : bool{
		return $this->ffa->isInFFA($player) || $this->ffa->isArenaWorld($player->getWorld());
	}
}
