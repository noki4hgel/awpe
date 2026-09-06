<?php

declare(strict_types=1);

namespace practice\nametag;

use practice\PlaceholderProvider;
use practice\PracticeConfig;
use practice\rank\RankManager;
use practice\session\Session;
use practice\settings\SettingRegistry;
use practice\settings\Settings;
use practice\util\Placeholder;

/**
 * Renders the line shown under a player's name (ping / device / health).
 *
 * The score tag is a synced entity property, so it is only pushed when the
 * formatted text actually changed.
 */
final class NameTagManager{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly PlaceholderProvider $placeholders,
		private readonly SettingRegistry $settings,
		private readonly RankManager $ranks
	){
	}

	public function isEnabled() : bool{
		return $this->config->getBool("nametag.enabled", true);
	}

	public function update(Session $session) : void{
		$player = $session->getPlayer();
		if(!$player->isConnected()){
			return;
		}

		$this->updateRankTag($session);

		if(!$this->isEnabled() || !$this->settings->isEnabled($session, Settings::NAMETAG)){
			$this->remove($session);
			return;
		}

		$text = Placeholder::apply(
			$this->config->getString("nametag.format"),
			$this->placeholders->forSession($session)
		);

		if($session->getLastNameTag() === $text){
			return;
		}

		$player->setScoreTag($text);
		$session->setLastNameTag($text);
	}

	/**
	 * The name itself, rendered through the player's rank. Separate from the
	 * score tag below it, and not something a player can switch off — it is how
	 * everyone else identifies him.
	 */
	private function updateRankTag(Session $session) : void{
		$rank = $this->ranks->get($session);
		if($rank === null){
			return;
		}

		$player = $session->getPlayer();
		$nameTag = Placeholder::apply($rank->getNameTagFormat(), ["player" => $player->getName()]);

		if($player->getNameTag() !== $nameTag){
			$player->setNameTag($nameTag);
		}
	}

	public function remove(Session $session) : void{
		if($session->getLastNameTag() === null){
			return;
		}

		$session->getPlayer()->setScoreTag("");
		$session->setLastNameTag(null);
	}
}
