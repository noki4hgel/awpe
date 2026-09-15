<?php

declare(strict_types=1);

namespace practice\combat;

use practice\entity\PracticeLightningBolt;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;
use practice\settings\SettingRegistry;
use practice\settings\Settings;
use practice\util\Placeholder;
use function count;

/**
 * What a kill looks like: the lightning where the loser fell and the banner on
 * the winner's screen.
 *
 * Shared by FFA and duels, so both feel the same and both honour the same
 * server switches and per-player settings.
 */
final class KillEffects{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly SessionManager $sessions,
		private readonly SettingRegistry $settings
	){
	}

	/**
	 * Call while the loser still stands where he died.
	 */
	public function play(Session $killer, Session $victim) : void{
		$this->strikeLightning($victim);
		$this->sendKillTitle($killer, $victim);
	}

	/**
	 * Cosmetic strike where the loser fell, sent only to the viewers who want
	 * to see it. Somebody whose chunks happen to be re-sent within the bolt's
	 * short lifetime may still catch it, which is harmless.
	 */
	public function strikeLightning(Session $victim) : void{
		if(!$this->config->getBool("combat.lightning-on-kill", true)){
			return;
		}

		$location = $victim->getPlayer()->getLocation();
		$viewers = [];
		foreach($location->getWorld()->getViewersForPosition($location) as $viewer){
			$viewerSession = $this->sessions->get($viewer);
			if($viewerSession !== null && $this->settings->isEnabled($viewerSession, Settings::LIGHTNING_ON_KILL)){
				$viewers[] = $viewer;
			}
		}

		$bolt = new PracticeLightningBolt($location);
		if(count($viewers) === 0){
			$bolt->close();
			return;
		}

		$bolt->showTo($viewers);
	}

	/**
	 * The banner across the killer's screen naming who he just took down.
	 *
	 * Only the killer sees it — it is a reward, not an announcement.
	 */
	public function sendKillTitle(Session $killer, Session $victim) : void{
		if(!$this->config->getBool("combat.kill-title.enabled", true) || !$this->settings->isEnabled($killer, Settings::KILL_TITLE)){
			return;
		}

		$title = $this->config->getMessage("kill-title");
		if($title === ""){
			return;
		}

		$values = ["victim" => $victim->getName(), "killer" => $killer->getName()];

		$killer->getPlayer()->sendTitle(
			Placeholder::apply($title, $values),
			Placeholder::apply($this->config->getMessage("kill-subtitle"), $values),
			$this->config->getInt("combat.kill-title.fade-in", 5),
			$this->config->getInt("combat.kill-title.stay", 20),
			$this->config->getInt("combat.kill-title.fade-out", 5)
		);
	}
}
