<?php

declare(strict_types=1);

namespace practice\duel\match;

use pocketmine\world\World;
use practice\duel\arena\DuelArena;
use practice\duel\Ladder;
use practice\session\Session;
use function spl_object_id;

/**
 * One match between two players.
 *
 * Holds the state and the numbers; MatchManager decides what happens next. Every
 * timer lives here as a plain value advanced once a second by one shared task,
 * so a match can never leave a stray countdown running after it ended.
 */
final class DuelMatch{

	private MatchState $state = MatchState::PREPARING;
	private ?World $world = null;
	private int $countdown;
	private int $fightStartedAt = 0;
	private int $endsAt = 0;

	/** @var array<int, MatchStats> player object id => stats */
	private array $stats = [];

	public function __construct(
		private readonly int $id,
		private readonly Ladder $ladder,
		private readonly DuelArena $arena,
		private readonly bool $ranked,
		private readonly Session $first,
		private readonly Session $second,
		int $countdownSeconds
	){
		$this->countdown = $countdownSeconds;
		$this->stats[spl_object_id($first->getPlayer())] = new MatchStats();
		$this->stats[spl_object_id($second->getPlayer())] = new MatchStats();
	}

	public function getId() : int{
		return $this->id;
	}

	public function getLadder() : Ladder{
		return $this->ladder;
	}

	public function getArena() : DuelArena{
		return $this->arena;
	}

	public function isRanked() : bool{
		return $this->ranked;
	}

	public function getFirst() : Session{
		return $this->first;
	}

	public function getSecond() : Session{
		return $this->second;
	}

	/**
	 * @return array{Session, Session}
	 */
	public function getParticipants() : array{
		return [$this->first, $this->second];
	}

	public function contains(Session $session) : bool{
		return $session === $this->first || $session === $this->second;
	}

	public function getOpponent(Session $session) : Session{
		return $session === $this->first ? $this->second : $this->first;
	}

	public function getStats(Session $session) : MatchStats{
		return $this->stats[spl_object_id($session->getPlayer())];
	}

	public function getState() : MatchState{
		return $this->state;
	}

	public function setState(MatchState $state) : void{
		$this->state = $state;
	}

	public function isFighting() : bool{
		return $this->state === MatchState::FIGHTING;
	}

	public function isClosed() : bool{
		return $this->state === MatchState::CLOSED;
	}

	public function getWorld() : ?World{
		return $this->world;
	}

	public function setWorld(World $world) : void{
		$this->world = $world;
	}

	/**
	 * Seconds of countdown still to show, and ticks one off.
	 */
	public function takeCountdownSecond() : int{
		return $this->countdown--;
	}

	public function getFightStartedAt() : int{
		return $this->fightStartedAt;
	}

	public function setFightStartedAt(int $time) : void{
		$this->fightStartedAt = $time;
	}

	public function getEndsAt() : int{
		return $this->endsAt;
	}

	public function setEndsAt(int $time) : void{
		$this->endsAt = $time;
	}
}
