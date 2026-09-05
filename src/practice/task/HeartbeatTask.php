<?php

declare(strict_types=1);

namespace practice\task;

use pocketmine\scheduler\Task;
use practice\bossbar\BossBarManager;
use practice\nametag\NameTagManager;
use practice\scoreboard\ScoreboardManager;
use practice\session\SessionManager;

/**
 * The core's only repeating task: refreshes every display that shows live data.
 *
 * Everything is driven from one tick so the sidebar, boss bar and nametag never
 * disagree about a player's ping or health.
 */
final class HeartbeatTask extends Task{

	public function __construct(
		private readonly SessionManager $sessions,
		private readonly ScoreboardManager $scoreboards,
		private readonly BossBarManager $bossBars,
		private readonly NameTagManager $nameTags
	){
	}

	public function onRun() : void{
		foreach($this->sessions->all() as $session){
			if(!$session->getPlayer()->isConnected()){
				continue;
			}

			$this->scoreboards->update($session);
			$this->bossBars->update($session);
			$this->nameTags->update($session);
		}
	}
}
