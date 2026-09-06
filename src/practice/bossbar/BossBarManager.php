<?php

declare(strict_types=1);

namespace practice\bossbar;

use practice\PlaceholderProvider;
use practice\PracticeConfig;
use practice\session\Session;
use practice\settings\SettingRegistry;
use practice\settings\Settings;
use practice\util\Placeholder;

/**
 * Keeps every player's boss bar in sync with the configured title/colour.
 */
final class BossBarManager{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly PlaceholderProvider $placeholders,
		private readonly SettingRegistry $settings
	){
	}

	public function isEnabled() : bool{
		return $this->config->getBool("bossbar.enabled", true);
	}

	public function update(Session $session) : void{
		$bossBar = $session->getBossBar();

		if(!$this->isEnabled() || !$this->settings->isEnabled($session, Settings::BOSS_BAR)){
			$bossBar->remove();
			return;
		}

		$bossBar->setColor(BossBar::colorFromString($this->config->getString("bossbar.color", "purple")));
		$bossBar->display(
			Placeholder::apply($this->config->getString("bossbar.title", "§cAWPE §eBedrock"), $this->placeholders->forSession($session)),
			$this->config->getFloat("bossbar.percentage", 1.0)
		);
	}

	public function remove(Session $session) : void{
		$session->getBossBar()->remove();
	}
}
