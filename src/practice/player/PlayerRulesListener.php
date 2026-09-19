<?php

declare(strict_types=1);

namespace practice\player;

use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerMissSwingEvent;
use pocketmine\player\Player;
use practice\combat\ClickTracker;
use practice\PracticeConfig;

/**
 * Server-wide player rules that are not tied to the lobby or to an arena.
 *
 * Hunger lives here rather than in the lobby listener because a practice server
 * wants it gone everywhere, arenas included.
 */
final class PlayerRulesListener implements Listener{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly ClickTracker $clicks
	){
	}

	/**
	 * Cancelling exhaustion stops the food bar from ever draining, which is
	 * cheaper and less jarring than topping it back up on a timer.
	 */
	public function onExhaust(PlayerExhaustEvent $event) : void{
		if(!$this->config->getBool("server.disable-hunger", true)){
			return;
		}

		$player = $event->getPlayer();
		if($player instanceof Player){
			$event->cancel();
		}
	}

	/**
	 * A swing that hit nothing still counts towards CPS; landed hits are
	 * recorded by the combat listener.
	 *
	 * Vanilla answers such a swing with a "whoosh", which at practice click
	 * speeds is a constant rattle. Cancelling the event silences it, but it
	 * also takes the arm swing with it, so the animation is sent again here -
	 * an opponent has to see the arm move.
	 */
	public function onMissSwing(PlayerMissSwingEvent $event) : void{
		$player = $event->getPlayer();
		$this->clicks->record($player);

		if($this->config->getBool("combat.disable-swing-sound", true)){
			$event->cancel();
			$player->broadcastAnimation(new ArmSwingAnimation($player), $player->getViewers());
		}
	}
}
