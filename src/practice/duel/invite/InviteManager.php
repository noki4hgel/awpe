<?php

declare(strict_types=1);

namespace practice\duel\invite;

use practice\duel\Ladder;
use practice\duel\LadderRegistry;
use practice\duel\match\MatchManager;
use practice\duel\queue\QueueManager;
use practice\PracticeConfig;
use practice\session\Session;
use practice\settings\SettingRegistry;
use practice\settings\Settings;
use practice\util\Placeholder;
use function explode;
use function max;
use function spl_object_id;
use function strtolower;
use function time;

/**
 * Direct challenges between players.
 *
 * An invite expires, only one can be pending between the same two players, and
 * a sender has to wait a little before challenging the same player again — so a
 * challenge cannot be turned into a way to spam somebody's screen. Players can
 * also turn challenges off in their settings.
 *
 * Invites are always unranked: a rating should come from the queue, not from
 * picking an opponent.
 */
final class InviteManager{

	/** @var array<int, array<int, Invite>> target player id => sender player id => invite */
	private array $invites = [];

	/** @var array<string, int> "sender:target" => when the last invite was sent */
	private array $lastSent = [];

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly LadderRegistry $ladders,
		private readonly MatchManager $matches,
		private readonly QueueManager $queue,
		private readonly SettingRegistry $settings
	){
	}

	/**
	 * @return string|null null when sent, otherwise the message key saying why not
	 */
	public function send(Session $from, Session $to, Ladder $ladder) : ?string{
		if($from === $to){
			return "duel-invite-self";
		}
		if(!$from->isInLobby() || $this->matches->isInMatch($from)){
			return "duel-invite-not-in-lobby";
		}
		if(!$to->isInLobby() || $this->matches->isInMatch($to)){
			return "duel-invite-target-busy";
		}
		if(!$this->settings->isEnabled($to, Settings::DUEL_REQUESTS)){
			return "duel-invite-disabled";
		}

		$now = time();
		$existing = $this->invites[self::id($to)][self::id($from)] ?? null;
		if($existing !== null && !$existing->isExpired($now, $this->timeout())){
			return "duel-invite-pending";
		}

		$pairKey = self::id($from) . ":" . self::id($to);
		if($now - ($this->lastSent[$pairKey] ?? 0) < $this->config->getInt("duels.invites.cooldown", 10)){
			return "duel-invite-cooldown";
		}

		$this->invites[self::id($to)][self::id($from)] = new Invite($from, $to, $ladder->getId(), $now);
		$this->lastSent[$pairKey] = $now;

		$values = [
			"bar" => $this->config->getMessage("duel-bar"),
			"player" => $from->getName(),
			"target" => $to->getName(),
			"ladder" => $ladder->getDisplay(),
			"timeout" => $this->timeout()
		];
		$this->notify($to, "duel-invite-received", $values);
		$to->getPlayer()->sendToastNotification(
			Placeholder::apply($this->config->getMessage("duel-invite-toast-title"), $values),
			Placeholder::apply($this->config->getMessage("duel-invite-toast-body"), $values)
		);
		$this->notify($from, "duel-invite-sent", $values);

		return null;
	}

	/**
	 * Accepts the invite from a named player, or the most recent one when no
	 * name is given.
	 *
	 * @return string|null null when the match was started, otherwise why not
	 */
	public function accept(Session $to, ?string $fromName) : ?string{
		$invite = $this->find($to, $fromName);
		if($invite === null){
			return "duel-invite-none";
		}

		$from = $invite->getFrom();
		$this->forgetPair($from, $to);

		if(!$from->getPlayer()->isConnected() || !$from->isInLobby() || $this->matches->isInMatch($from)){
			return "duel-invite-sender-busy";
		}
		if(!$to->isInLobby() || $this->matches->isInMatch($to)){
			return "duel-invite-not-in-lobby";
		}

		$ladder = $this->ladders->get($invite->getLadderId());
		if($ladder === null){
			return "duel-invite-none";
		}

		$this->queue->leave($from, false);
		$this->queue->leave($to, false);
		$this->notify($from, "duel-invite-accepted", ["player" => $to->getName(), "ladder" => $ladder->getDisplay()]);

		return $this->matches->start($ladder, false, $from, $to) ? null : "duel-invite-failed";
	}

	/**
	 * @return string|null null when declined, otherwise why not
	 */
	public function deny(Session $to, ?string $fromName) : ?string{
		$invite = $this->find($to, $fromName);
		if($invite === null){
			return "duel-invite-none";
		}

		$this->forgetPair($invite->getFrom(), $to);
		$this->notify($invite->getFrom(), "duel-invite-denied", ["player" => $to->getName()]);

		return null;
	}

	/**
	 * Drops every invite a player sent or received, for when he leaves.
	 */
	public function clear(Session $session) : void{
		$id = self::id($session);
		unset($this->invites[$id]);
		foreach($this->invites as $target => $bySender){
			unset($this->invites[$target][$id]);
		}
		foreach($this->lastSent as $key => $time){
			[$sender, $target] = explode(":", $key);
			if((int) $sender === $id || (int) $target === $id){
				unset($this->lastSent[$key]);
			}
		}
	}

	/**
	 * Called once a second.
	 */
	public function expire() : void{
		$now = time();
		foreach($this->invites as $target => $bySender){
			foreach($bySender as $sender => $invite){
				if($invite->isExpired($now, $this->timeout())){
					unset($this->invites[$target][$sender]);
				}
			}
		}
	}

	private function find(Session $to, ?string $fromName) : ?Invite{
		$now = time();
		$latest = null;
		foreach($this->invites[self::id($to)] ?? [] as $invite){
			if($invite->isExpired($now, $this->timeout())){
				continue;
			}
			if($fromName !== null && strtolower($invite->getFrom()->getName()) !== strtolower($fromName)){
				continue;
			}
			if($latest === null || $invite->getSentAt() >= $latest->getSentAt()){
				$latest = $invite;
			}
		}

		return $latest;
	}

	/**
	 * Accepting or declining settles both directions between the two players.
	 */
	private function forgetPair(Session $a, Session $b) : void{
		unset($this->invites[self::id($a)][self::id($b)], $this->invites[self::id($b)][self::id($a)]);
	}

	private function timeout() : int{
		return max(1, $this->config->getInt("duels.invites.timeout", 60));
	}

	/**
	 * @param array<string, string|int> $values
	 */
	private function notify(Session $session, string $key, array $values) : void{
		$message = $this->config->getMessage($key);
		if($message !== "" && $session->getPlayer()->isConnected()){
			$session->getPlayer()->sendMessage(Placeholder::apply($message, $values));
		}
	}

	private static function id(Session $session) : int{
		return spl_object_id($session->getPlayer());
	}
}
