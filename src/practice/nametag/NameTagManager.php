<?php

declare(strict_types=1);

namespace practice\nametag;

use practice\PlaceholderProvider;
use practice\PracticeConfig;
use practice\session\Session;
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
		private readonly PlaceholderProvider $placeholders
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

		if(!$this->isEnabled()){
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

	public function remove(Session $session) : void{
		if($session->getLastNameTag() === null){
			return;
		}

		$session->getPlayer()->setScoreTag("");
		$session->setLastNameTag(null);
	}
}
