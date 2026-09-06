<?php

declare(strict_types=1);

namespace practice\task;

use pocketmine\scheduler\Task;
use practice\bossbar\BossBarManager;
use practice\combat\ClickTracker;
use practice\combat\CombatManager;
use practice\leaderboard\LeaderboardManager;
use practice\nametag\NameTagManager;
use practice\PracticeConfig;
use practice\scoreboard\ScoreboardManager;
use practice\session\Session;
use practice\session\SessionManager;
use practice\settings\SettingRegistry;
use practice\settings\Settings;
use practice\util\Placeholder;

/**
 * The core's only repeating task: refreshes every display that shows live data.
 *
 * Everything is driven from one tick so the sidebar, boss bar, nametag and
 * leaderboards never disagree about a player's ping, health or kill count.
 */
final class HeartbeatTask extends Task{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly SessionManager $sessions,
		private readonly ScoreboardManager $scoreboards,
		private readonly BossBarManager $bossBars,
		private readonly NameTagManager $nameTags,
		private readonly LeaderboardManager $leaderboards,
		private readonly SettingRegistry $settings,
		private readonly CombatManager $combat,
		private readonly ClickTracker $clicks
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
			$this->sendClicksPerSecond($session);
		}

		$this->leaderboards->refresh();
	}

	/**
	 * The CPS read-out shares the action bar with the hit-distance messages, so
	 * it stands down while the player is in combat — those are the more useful
	 * of the two mid-fight, and they are sent on every hit anyway.
	 */
	private function sendClicksPerSecond(Session $session) : void{
		if(!$this->settings->isEnabled($session, Settings::CPS_COUNTER)){
			return;
		}

		$player = $session->getPlayer();
		if($this->combat->isTagged($player)){
			return;
		}

		$message = $this->config->getMessage("cps-counter");
		if($message === ""){
			return;
		}

		$player->sendActionBarMessage(Placeholder::apply($message, [
			"cps" => $this->clicks->getClicksPerSecond($player)
		]));
	}
}
