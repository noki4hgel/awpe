<?php

declare(strict_types=1);

namespace practice\session;

use pocketmine\player\Player;
use practice\scoreboard\Scoreboard;
use practice\stats\StatsProvider;
use function count;
use function spl_object_id;

/**
 * Owns one Session per online player.
 *
 * Sessions are keyed by object id rather than by name, so a reconnecting player
 * can never inherit the state of the connection he replaced.
 */
final class SessionManager{

	/** @var array<int, Session> */
	private array $sessions = [];

	public function __construct(
		private readonly StatsProvider $stats,
		private readonly string $scoreboardSlot = Scoreboard::SLOT_SIDEBAR
	){
	}

	public function open(Player $player) : Session{
		return $this->sessions[spl_object_id($player)] ??= new Session($player, $this->stats, $this->scoreboardSlot);
	}

	public function close(Player $player) : void{
		$id = spl_object_id($player);
		if(isset($this->sessions[$id])){
			$this->sessions[$id]->save();
			unset($this->sessions[$id]);
		}
	}

	public function has(Player $player) : bool{
		return isset($this->sessions[spl_object_id($player)]);
	}

	public function get(Player $player) : ?Session{
		return $this->sessions[spl_object_id($player)] ?? null;
	}

	/**
	 * Convenience for listeners that only run for players who already have a
	 * session; returns null when the player is still logging in.
	 */
	public function getOrNull(?Player $player) : ?Session{
		return $player === null ? null : $this->get($player);
	}

	/**
	 * @return Session[]
	 */
	public function all() : array{
		return $this->sessions;
	}

	public function count() : int{
		return count($this->sessions);
	}

	public function saveAll() : void{
		foreach($this->sessions as $session){
			$session->save();
		}
	}

	public function getStats() : StatsProvider{
		return $this->stats;
	}
}
