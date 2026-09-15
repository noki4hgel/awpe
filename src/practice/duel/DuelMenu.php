<?php

declare(strict_types=1);

namespace practice\duel;

use pocketmine\player\Player;
use practice\duel\invite\InviteManager;
use practice\duel\match\MatchManager;
use practice\duel\queue\QueueManager;
use practice\form\SimpleForm;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;
use practice\util\Placeholder;
use function array_values;
use function count;

/**
 * The duel forms: pick unranked or ranked, pick a mode, queue — or pick the
 * mode for a challenge.
 *
 * Every button shows how many are waiting and playing, so a player can see
 * where a match will come fastest.
 */
final class DuelMenu{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly SessionManager $sessions,
		private readonly LadderRegistry $ladders,
		private readonly QueueManager $queue,
		private readonly MatchManager $matches,
		private readonly InviteManager $invites
	){
	}

	public function openModes(Session $session) : void{
		if(count($this->ladders->all()) === 0){
			$this->send($session, "duel-no-ladders", []);
			return;
		}

		$form = new SimpleForm(
			$this->config->getString("duels.form.title", "Duels"),
			$this->config->getString("duels.form.content")
		);

		$modes = [false, true];
		foreach($modes as $ranked){
			$form->addButton(
				Placeholder::apply($this->config->getString("duels.form.mode-button", "{mode}\n{queued} queued"), [
					"mode" => $this->config->getMessage($ranked ? "duel-mode-ranked" : "duel-mode-unranked"),
					"queued" => $this->countQueued($ranked)
				]),
				$this->config->getString($ranked ? "duels.form.ranked-icon" : "duels.form.unranked-icon")
			);
		}

		$form->setHandler(function(Player $player, ?int $selected) use ($modes) : void{
			$session = $this->sessions->get($player);
			if($selected === null || $session === null || !isset($modes[$selected])){
				return;
			}
			$this->openLadders($session, $modes[$selected]);
		});

		$session->getPlayer()->sendForm($form);
	}

	public function openLadders(Session $session, bool $ranked) : void{
		$ladders = array_values($ranked ? $this->ladders->ranked() : $this->ladders->all());
		if(count($ladders) === 0){
			$this->send($session, "duel-no-ladders", []);
			return;
		}

		$form = new SimpleForm(Placeholder::apply($this->config->getString("duels.form.ladders-title", "{mode}"), [
			"mode" => $this->config->getMessage($ranked ? "duel-mode-ranked" : "duel-mode-unranked")
		]));

		foreach($ladders as $ladder){
			$form->addButton($this->ladderButton($ladder, $ranked, $session), $ladder->getIcon());
		}

		$form->setHandler(function(Player $player, ?int $selected) use ($ladders, $ranked) : void{
			$session = $this->sessions->get($player);
			if($selected === null || $session === null || !isset($ladders[$selected])){
				return;
			}

			$error = $this->queue->join($session, $ladders[$selected], $ranked);
			if($error !== null){
				$this->send($session, $error, []);
			}
		});

		$session->getPlayer()->sendForm($form);
	}

	/**
	 * The mode picker behind "/duel <player>".
	 */
	public function openChallenge(Session $from, Session $to) : void{
		$ladders = array_values($this->ladders->all());
		if(count($ladders) === 0){
			$this->send($from, "duel-no-ladders", []);
			return;
		}

		$form = new SimpleForm(Placeholder::apply($this->config->getString("duels.form.challenge-title", "{player}"), [
			"player" => $to->getName()
		]));

		foreach($ladders as $ladder){
			$form->addButton($ladder->getDisplay(), $ladder->getIcon());
		}

		$form->setHandler(function(Player $player, ?int $selected) use ($ladders, $to) : void{
			$from = $this->sessions->get($player);
			if($selected === null || $from === null || !isset($ladders[$selected])){
				return;
			}
			if(!$to->getPlayer()->isConnected()){
				$this->send($from, "duel-invite-target-busy", ["player" => $to->getName()]);
				return;
			}

			$error = $this->invites->send($from, $to, $ladders[$selected]);
			if($error !== null){
				$this->send($from, $error, ["player" => $to->getName(), "ladder" => $ladders[$selected]->getDisplay()]);
			}
		});

		$from->getPlayer()->sendForm($form);
	}

	private function ladderButton(Ladder $ladder, bool $ranked, Session $session) : string{
		return Placeholder::apply($this->config->getString("duels.form.ladder-button", "{ladder}\n{queued} queued | {playing} playing"), [
			"ladder" => $ladder->getDisplay(),
			"queued" => $this->queue->count($ladder->getId(), $ranked),
			"playing" => $this->matches->countPlaying($ladder->getId()),
			"elo" => $session->getData()->getDuelElo($ladder->getId(), $this->config->getInt("duels.ranked.starting-elo", 1000))
		]);
	}

	private function countQueued(bool $ranked) : int{
		$total = 0;
		foreach($this->ladders->all() as $ladder){
			$total += $this->queue->count($ladder->getId(), $ranked);
		}

		return $total;
	}

	/**
	 * @param array<string, string|int> $values
	 */
	private function send(Session $session, string $key, array $values) : void{
		$message = $this->config->getMessage($key);
		if($message !== ""){
			$session->getPlayer()->sendMessage(Placeholder::apply($message, $values));
		}
	}
}
