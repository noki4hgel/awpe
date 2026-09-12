<?php

declare(strict_types=1);

namespace practice\task;

use pocketmine\scheduler\Task;
use practice\cooldown\XpBarCooldown;
use practice\session\SessionManager;

/**
 * Drives the experience-bar cooldown read-out.
 *
 * It runs far more often than the heartbeat because a bar that only moves once
 * a second looks broken; the work per player is an integer comparison, so the
 * rate costs nothing.
 */
final class XpBarCooldownTask extends Task{

	public function __construct(
		private readonly SessionManager $sessions,
		private readonly XpBarCooldown $display
	){
	}

	public function onRun() : void{
		foreach($this->sessions->all() as $session){
			$this->display->update($session->getPlayer());
		}
	}
}
