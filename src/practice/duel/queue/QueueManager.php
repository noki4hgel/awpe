<?php

declare(strict_types=1);

namespace practice\duel\queue;

use practice\duel\Ladder;
use practice\duel\LadderRegistry;
use practice\duel\match\MatchManager;
use practice\lobby\LobbyManager;
use practice\PracticeConfig;
use practice\session\Session;
use practice\util\Placeholder;
use function count;
use function intdiv;
use function spl_object_id;
use function sprintf;
use function time;

/**
 * Who is waiting for which ladder, and turning pairs into matches.
 *
 * A queued player stays in the lobby with the lobby's protections; only his
 * hotbar changes, to an item that leaves the queue. Anyone who wanders off —
 * into FFA, into a match, offline — is dropped on the next tick rather than
 * every one of those paths having to remember the queue exists.
 */
final class QueueManager{

	public const LAYOUT_QUEUE = "queue";

	/** @var array<int, array{entry: QueueEntry, session: Session}> player object id => entry */
	private array $queue = [];

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly LadderRegistry $ladders,
		private readonly Matchmaker $matchmaker,
		private readonly MatchManager $matches,
		private readonly LobbyManager $lobby
	){
	}

	/**
	 * @return string|null null on success, otherwise the message key saying why not
	 */
	public function join(Session $session, Ladder $ladder, bool $ranked) : ?string{
		if(!$session->isInLobby() || $this->matches->isInMatch($session)){
			return "duel-queue-not-in-lobby";
		}
		if($ranked && !$ladder->isRankedAllowed()){
			return "duel-queue-unranked-only";
		}

		$this->leave($session, false);

		$elo = $session->getData()->getDuelElo($ladder->getId(), $this->config->getInt("duels.ranked.starting-elo", 1000));
		$this->queue[spl_object_id($session->getPlayer())] = [
			"entry" => new QueueEntry(spl_object_id($session->getPlayer()), $ladder->getId(), $ranked, $elo, time()),
			"session" => $session
		];

		$this->lobby->sendItems($session, self::LAYOUT_QUEUE);
		$this->send($session, "duel-queue-joined", $this->describeFor($session));

		return null;
	}

	/**
	 * @return bool whether the player was queued at all
	 */
	public function leave(Session $session, bool $notify = true) : bool{
		$key = spl_object_id($session->getPlayer());
		if(!isset($this->queue[$key])){
			return false;
		}

		$values = $this->describeFor($session);
		unset($this->queue[$key]);

		if($session->isInLobby() && $session->getPlayer()->isConnected()){
			$this->lobby->sendItems($session);
		}
		if($notify){
			$this->send($session, "duel-queue-left", $values);
		}

		return true;
	}

	/**
	 * Forgets a player without touching his hotbar, for a disconnect.
	 */
	public function forget(Session $session) : void{
		unset($this->queue[spl_object_id($session->getPlayer())]);
	}

	public function isQueued(Session $session) : bool{
		return isset($this->queue[spl_object_id($session->getPlayer())]);
	}

	public function getEntry(Session $session) : ?QueueEntry{
		return $this->queue[spl_object_id($session->getPlayer())]["entry"] ?? null;
	}

	public function count(string $ladderId, bool $ranked) : int{
		$count = 0;
		foreach($this->queue as ["entry" => $entry]){
			if($entry->getLadderId() === $ladderId && $entry->isRanked() === $ranked){
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Called once a second: drops stale entries, then starts every match that
	 * can be made.
	 */
	public function tick() : void{
		foreach($this->queue as $key => ["session" => $session]){
			if(!$session->getPlayer()->isConnected() || !$session->isInLobby() || $this->matches->isInMatch($session)){
				unset($this->queue[$key]);
			}
		}

		if(count($this->queue) < 2){
			return;
		}

		$entries = [];
		foreach($this->queue as ["entry" => $entry]){
			$entries[] = $entry;
		}

		foreach($this->matchmaker->pair($entries, time()) as [$first, $second]){
			$firstSession = $this->queue[$first->getKey()]["session"] ?? null;
			$secondSession = $this->queue[$second->getKey()]["session"] ?? null;
			$ladder = $this->ladders->get($first->getLadderId());
			if($firstSession === null || $secondSession === null || $ladder === null){
				continue;
			}

			$this->leave($firstSession, false);
			$this->leave($secondSession, false);
			$this->matches->start($ladder, $first->isRanked(), $firstSession, $secondSession);
		}
	}

	/**
	 * @return array<string, string|int>
	 */
	public function describeFor(Session $session) : array{
		$entry = $this->getEntry($session);
		if($entry === null){
			return ["queue_ladder" => "", "queue_mode" => "", "queue_time" => "0:00", "queue_elo" => 0];
		}

		$waited = time() - $entry->getJoinedAt();

		return [
			"queue_ladder" => $this->ladders->get($entry->getLadderId())?->getDisplay() ?? $entry->getLadderId(),
			"queue_mode" => $this->config->getMessage($entry->isRanked() ? "duel-mode-ranked" : "duel-mode-unranked"),
			"queue_time" => sprintf("%d:%02d", intdiv($waited, 60), $waited % 60),
			"queue_elo" => $entry->getElo()
		];
	}

	/**
	 * @param array<string, string|int> $values
	 */
	private function send(Session $session, string $key, array $values) : void{
		$message = $this->config->getMessage($key);
		if($message !== "" && $session->getPlayer()->isConnected()){
			$session->getPlayer()->sendMessage(Placeholder::apply($message, $values));
		}
	}
}
