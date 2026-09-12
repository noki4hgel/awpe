<?php

declare(strict_types=1);

namespace practice\cooldown;

use pocketmine\player\Player;
use function arsort;
use function array_key_first;
use function ceil;

/**
 * Shows the running item cooldown on the experience bar: the level is the
 * seconds left and the bar drains as the wait runs out.
 *
 * This is the read-out practice players expect for ender pearls — it is visible
 * without looking at the hotbar and precise enough to time a chase.
 *
 * Only item cooldowns reach the bar. Internal timers share the same manager
 * (the chat limiter, for one) and counting those down in front of the player
 * would be nonsense.
 *
 * The display deliberately keeps no state of its own. When nothing is running
 * it clears a non-zero bar and stops, which is correct because the core resets
 * experience to zero on every state change and nothing else here awards any.
 */
final class XpBarCooldown{

	private const TICKS_PER_SECOND = 20;

	public function __construct(
		private readonly CooldownManager $cooldowns,
		private readonly ItemCooldowns $items
	){
	}

	public function update(Player $player) : void{
		if(!$player->isConnected()){
			return;
		}

		$tag = $this->pickTag($player);
		if($tag === null){
			$this->clear($player);
			return;
		}

		$player->getXpManager()->setXpAndProgressNoEvent(
			(int) ceil($this->cooldowns->getRemainingTicks($player, $tag) / self::TICKS_PER_SECOND),
			$this->cooldowns->getRemainingFraction($player, $tag)
		);
	}

	/**
	 * Puts the bar back to empty, but only if something is actually on it — so
	 * this costs one comparison per tick for a player who is not waiting.
	 */
	public function clear(Player $player) : void{
		$xp = $player->getXpManager();
		if($xp->getXpLevel() === 0 && $xp->getXpProgress() <= 0.0){
			return;
		}

		$xp->setXpAndProgressNoEvent(0, 0.0);
	}

	/**
	 * The item cooldown with the most time left — the one the player is
	 * actually waiting on when several overlap.
	 */
	private function pickTag(Player $player) : ?string{
		$running = [];
		foreach($this->cooldowns->getRunningTags($player) as $tag => $remaining){
			if($this->items->isItemTag($tag)){
				$running[$tag] = $remaining;
			}
		}

		arsort($running);

		return array_key_first($running);
	}
}
