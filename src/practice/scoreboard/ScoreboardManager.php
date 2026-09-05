<?php

declare(strict_types=1);

namespace practice\scoreboard;

use practice\PlaceholderProvider;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionState;
use practice\util\Placeholder;
use function count;

/**
 * Decides what the sidebar says. The Scoreboard object below only knows how to
 * push lines to a client; which lines those are lives here.
 */
final class ScoreboardManager{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly PlaceholderProvider $placeholders
	){
	}

	public function isEnabled() : bool{
		return $this->config->getBool("scoreboard.enabled", true);
	}

	public function update(Session $session) : void{
		$scoreboard = $session->getScoreboard();

		if(!$this->isEnabled()){
			$scoreboard->remove();
			return;
		}

		$lines = $this->getLines($session->getState());
		if(count($lines) === 0){
			$scoreboard->remove();
			return;
		}

		$values = $this->placeholders->forSession($session);
		$title = Placeholder::apply($this->config->getString("scoreboard.title", "§l§fAWPE"), $values);

		$scoreboard->display($title, Placeholder::applyAll($lines, $values));
	}

	public function remove(Session $session) : void{
		$session->getScoreboard()->remove();
	}

	/**
	 * @return string[]
	 */
	private function getLines(SessionState $state) : array{
		$key = match($state){
			SessionState::LOBBY => "lobby",
			SessionState::FFA => "ffa",
		};

		$lines = $this->config->getStringList("scoreboard.lines." . $key);

		return count($lines) > 0 ? $lines : $this->config->getStringList("scoreboard.lines.lobby");
	}
}
