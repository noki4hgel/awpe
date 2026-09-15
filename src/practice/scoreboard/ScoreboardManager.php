<?php

declare(strict_types=1);

namespace practice\scoreboard;

use practice\PlaceholderProvider;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionState;
use practice\duel\queue\QueueManager;
use practice\settings\SettingRegistry;
use practice\settings\Settings;
use practice\util\Placeholder;
use function count;

/**
 * Decides what the sidebar says. The Scoreboard object below only knows how to
 * push lines to a client; which lines those are lives here.
 */
final class ScoreboardManager{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly PlaceholderProvider $placeholders,
		private readonly SettingRegistry $settings,
		private readonly QueueManager $queue
	){
	}

	public function isEnabled() : bool{
		return $this->config->getBool("scoreboard.enabled", true);
	}

	public function update(Session $session) : void{
		$scoreboard = $session->getScoreboard();

		if(!$this->isEnabled() || !$this->settings->isEnabled($session, Settings::SCOREBOARD)){
			$scoreboard->remove();
			return;
		}

		$lines = $this->getLines($session);
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
	private function getLines(Session $session) : array{
		$key = match($session->getState()){
			//A queued player is still in the lobby, but his sidebar says what he waits for.
			SessionState::LOBBY => $this->queue->isQueued($session) ? "queue" : "lobby",
			SessionState::FFA => "ffa",
			SessionState::DUEL => "duel",
		};

		$lines = $this->config->getStringList("scoreboard.lines." . $key);

		return count($lines) > 0 ? $lines : $this->config->getStringList("scoreboard.lines.lobby");
	}
}
